<?php
/**
 * 后台用户管理：列表/建号/编辑/角色授予/解锁/禁用/封禁/注销（仅管理员，能力点 manage_users）
 * 红线：editor/admin 角色只能由管理员在此授予；公开注册只产生 user
 */
defined('APP_BOOT') or exit;

class AdminUser
{
    /** 用户列表 */
    public static function listAction()
    {
        Auth::require_cap('manage_users');
        $page = max(1, input_int('page', 1, 'get'));
        $perPage = 15;
        $total = DB::query('users')->count();
        $totalPages = max(1, (int) ceil($total / $perPage));
        // 越界页码钳制到末页，避免空表格
        $page = min($page, $totalPages);
        $users = DB::query('users')
            ->orderBy('id', 'ASC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->select();
        Admin::render(admin_t('admin.menu.user'), 'user_list', array(
            'users' => $users, 'page' => $page, 'totalPages' => $totalPages,
            // 安装管理员受保护不可封禁/注销，视图需据此隐藏操作按钮
            'rootId' => self::rootAdminId(),
        ));
    }

    /** 用户编辑页（新建/修改） */
    public static function editAction()
    {
        Auth::require_cap('manage_users');
        $id = input_int('id', 0, 'get');
        $user = null;
        if ($id > 0) {
            $user = DB::query('users')->where('id', '=', $id)->first();
            if (!$user) {
                flash_set('error', admin_t('admin.user.not_found'));
                redirect(site_base_admin('user/list'));
            }
        }
        Admin::render($id > 0 ? admin_t('admin.user.edit_title') : admin_t('admin.user.add'), 'user_edit', array(
            'user' => $user,
            // 安装管理员（安装程序创建的首位管理员）角色与状态不可变更
            'isRoot' => $user !== null && (int) $user['id'] === self::rootAdminId(),
        ));
    }

    /** 保存用户（新建/更新） */
    public static function saveAction()
    {
        Auth::require_cap('manage_users');
        $id = input_int('id', 0, 'post');
        $nickname = input_text('nickname', '', 64, 'post');
        $email = input_email('email', '', 'post');
        $phone = input_phone('phone', '', 'post');
        $role = input_enum('role', array('user', 'editor', 'admin'), 'user', 'post');
        $status = input_int('status', 1, 'post') === 1 ? 1 : 0;
        $forceChange = input_int('force_change', 0, 'post') === 1;
        $newPassword = input_password('new_password');

        if ($id > 0) {
            self::updateUser($id, $nickname, $email, $phone, $role, $status, $forceChange, $newPassword);
        } else {
            self::createUser($nickname, $email, $phone, $role);
        }
    }

    /** 新建用户：随机初始密码（一次性展示，不落日志；始终强制首登改密） */
    private static function createUser($nickname, $email, $phone, $role)
    {
        $username = input_text('username', '', 32, 'post');
        if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) {
            flash_set('error', admin_t('admin.user.username_format'));
            redirect(site_base_admin('user/edit'));
        }
        if (DB::query('users')->where('username', '=', $username)->value('id')) {
            flash_set('error', admin_t('admin.user.username_taken'));
            redirect(site_base_admin('user/edit'));
        }
        if ($email !== '' && DB::query('users')->where('email', '=', $email)->value('id')) {
            flash_set('error', admin_t('admin.user.email_taken'));
            redirect(site_base_admin('user/edit'));
        }
        // 手机号作为身份核验凭据必须全局唯一
        if ($phone !== '' && DB::query('users')->where('phone', '=', $phone)->value('id')) {
            flash_set('error', admin_t('admin.user.phone_taken'));
            redirect(site_base_admin('user/edit'));
        }
        $password = self::generateStrongPassword();
        $err = Auth::validate_password_strength($password, $username);
        if ($err !== '') {
            flash_set('error', admin_t('admin.user.gen_pwd_error', array($err)));
            redirect(site_base_admin('user/edit'));
        }
        // 前置唯一性查重存在并发窗口（TOCTOU）：撞唯一索引时捕获重复键异常转为友好提示，
        // 避免裸 PDOException 暴露 500
        try {
            $newId = DB::insert('users', array(
                'username'            => $username,
                'nickname'            => $nickname !== '' ? $nickname : $username,
                'password'            => password_hash($password, PASSWORD_DEFAULT),
                'email'               => $email !== '' ? $email : null,
                'phone'               => $phone,
                'avatar'              => '',
                'role'                => $role,
                'status'              => 1,
                // 初始密码经 Flash 明文回显一次，必须强制首登改密：
                // 改密时间置为过期阈值之前，登录后将被强制跳转改密页，防止初始密码长期有效
                'password_changed_at' => '2000-01-01 00:00:00',
                'login_fail'          => 0,
                'created_at'          => now(),
            ));
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                blog_log('user', 'user.create', 'fail', array('username' => $username, 'reason' => 'duplicate'));
                flash_set('error', '用户名/邮箱/手机号已被占用（并发冲突），请重试');
                redirect(site_base_admin('user/edit'));
            }
            throw $ex;
        }
        blog_log('user', 'user.create', 'success', array(
            'user_id' => $newId, 'username' => $username, 'role' => $role,
        ));
        flash_set('success', admin_t('admin.user.created_with_pwd', array($password)));
        redirect(site_base_admin('user/list'));
    }

    /** 更新用户资料/角色/状态/密码 */
    private static function updateUser($id, $nickname, $email, $phone, $role, $status, $forceChange, $newPassword)
    {
        $user = DB::query('users')->where('id', '=', $id)->first();
        if (!$user) {
            flash_set('error', admin_t('admin.user.not_found'));
            redirect(site_base_admin('user/list'));
        }
        $isSelf = (int) $user['id'] === Auth::id();
        // 敏感操作（重置密码/改绑邮箱/改绑手机）必须重验操作者当前密码（等保二级）：
        // 防止管理员会话被劫持或短暂无人值守时被用来接管其它账号
        $oldEmail = $user['email'] !== null ? $user['email'] : '';
        $sensitive = $newPassword !== ''
            || $email !== $oldEmail
            || $phone !== (string) $user['phone'];
        if ($sensitive) {
            $currentPassword = input_password('current_password');
            $operator = Auth::user();
            if ($currentPassword === '' || !password_verify($currentPassword, $operator['password'])) {
                // 操作者密码重验失败限流：复用登录失败计数，达到阈值即强制踢下线，
                // 防止会话被劫持后以该入口无限爆破操作者密码
                $fail = DB::query('users')->where('id', '=', (int) $operator['id'])->increment('login_fail');
                if ($fail !== false && $fail >= max(1, (int) Option::get('login_max_fail', 5))) {
                    blog_log('security', 'operator_reauth.lockout', 'fail', array('user_id' => (int) $operator['id']));
                    Auth::logout(); // 强制踢下线
                    redirect(Router::url('login'));
                }
                blog_log('user', 'user.update', 'fail', array(
                    'target_user_id' => $id, 'reason' => 'operator_password_wrong',
                ));
                flash_set('error', admin_t('admin.user.operator_pwd_required'));
                redirect(site_base_admin('user/edit&id=' . $id));
            }
        }
        // 自我保护：不得禁用/降权自己，避免管理员把自己锁出后台
        if ($isSelf && ($status !== 1 || $role !== 'admin')) {
            flash_set('error', admin_t('admin.user.self_protect'));
            redirect(site_base_admin('user/edit&id=' . $id));
        }
        // 安装管理员保护：安装程序创建的首位管理员不得被降权或禁用，防止误操作
        if ((int) $user['id'] === self::rootAdminId() && ($role !== 'admin' || $status !== 1)) {
            flash_set('error', admin_t('admin.user.root_protect'));
            redirect(site_base_admin('user/edit&id=' . $id));
        }
        // 最后一位在职管理员不得被降权或禁用
        // TODO: 存在 TOCTOU 竞态风险（检查与更新之间并发操作可能同时通过），
        // 建议在 DB 层添加事务支持后加锁复检；当前缓解措施：操作日志记录 + 管理员操作审计
        if ($user['role'] === 'admin' && ($role !== 'admin' || $status !== 1) && self::adminCount() <= 1) {
            flash_set('error', admin_t('admin.user.last_admin'));
            redirect(site_base_admin('user/edit&id=' . $id));
        }
        if ($email !== '' && $email !== $user['email']) {
            $exists = DB::query('users')->where('email', '=', $email)->where('id', '!=', $id)->value('id');
            if ($exists) {
                flash_set('error', admin_t('admin.user.email_taken'));
                redirect(site_base_admin('user/edit&id=' . $id));
            }
        }
        if ($phone !== '' && $phone !== $user['phone']) {
            $exists = DB::query('users')->where('phone', '=', $phone)->where('id', '!=', $id)->value('id');
            if ($exists) {
                flash_set('error', admin_t('admin.user.phone_taken'));
                redirect(site_base_admin('user/edit&id=' . $id));
            }
        }

        $update = array(
            'nickname' => $nickname !== '' ? $nickname : $user['nickname'],
            'email'    => $email !== '' ? $email : null,
            'phone'    => $phone,
            'role'     => $role,
            'status'   => $status,
        );
        DB::update('users', $update, array('id' => $id));
        $detail = array('target_user_id' => $id, 'role' => $role, 'status' => $status);
        blog_log('user', 'user.update', 'success', $detail);
        if ($role !== $user['role']) {
            // 角色变更必须单独留痕（等保审计要求）
            blog_log('user', 'user.role_change', 'success', array(
                'target_user_id' => $id, 'from' => $user['role'], 'to' => $role,
            ));
        }

        // 管理员重置密码（可选）
        if ($newPassword !== '') {
            $err = Auth::validate_password_strength($newPassword, $user['username']);
            if ($err !== '') {
                flash_set('error', $err);
                redirect(site_base_admin('user/edit&id=' . $id));
            }
            DB::update('users', array(
                'password'            => password_hash($newPassword, PASSWORD_DEFAULT),
                'password_changed_at' => $forceChange ? '2000-01-01 00:00:00' : now(),
            ), array('id' => $id));
            blog_log('user', 'user.password_reset', 'success', array('target_user_id' => $id));
            flash_set('success', admin_t('admin.user.saved_pwd_reset'));
        } else {
            if ($forceChange) {
                DB::update('users', array('password_changed_at' => '2000-01-01 00:00:00'), array('id' => $id));
            }
            flash_set('success', admin_t('admin.user.saved'));
        }
        redirect(site_base_admin('user/list'));
    }

    /** 手动解除账号锁定 */
    public static function unlockAction()
    {
        Auth::require_cap('manage_users');
        $id = input_int('id', 0, 'post');
        if ($id > 0) {
            DB::update('users', array('locked_until' => null, 'login_fail' => 0), array('id' => $id));
            blog_log('user', 'user.unlock', 'success', array('target_user_id' => $id));
            flash_set('success', admin_t('admin.user.unlocked'));
        }
        redirect(site_base_admin('user/list'));
    }

    /** 封禁用户：已发布内容保留，但禁止登录（既有会话立即失效） */
    public static function banAction()
    {
        Auth::require_cap('manage_users');
        $user = self::targetUser(input_int('id', 0, 'post'));
        if (!$user) {
            flash_set('error', admin_t('admin.user.not_found'));
            redirect(site_base_admin('user/list'));
        }
        $id = (int) $user['id'];
        if ($id === Auth::id()) {
            flash_set('error', admin_t('admin.user.self_ban'));
            redirect(site_base_admin('user/list'));
        }
        if ($id === self::rootAdminId()) {
            flash_set('error', admin_t('admin.user.root_ban'));
            redirect(site_base_admin('user/list'));
        }
        if ($user['role'] === 'admin' && self::adminCount() <= 1) {
            flash_set('error', admin_t('admin.user.last_admin_avail'));
            redirect(site_base_admin('user/list'));
        }
        DB::update('users', array('is_banned' => 1), array('id' => $id));
        blog_log('user', 'user.ban', 'success', array('target_user_id' => $id));
        flash_set('success', admin_t('admin.user.banned'));
        redirect(site_base_admin('user/list'));
    }

    /** 解除封禁 */
    public static function unbanAction()
    {
        Auth::require_cap('manage_users');
        $id = input_int('id', 0, 'post');
        if ($id > 0) {
            DB::update('users', array('is_banned' => 0), array('id' => $id));
            blog_log('user', 'user.unban', 'success', array('target_user_id' => $id));
            flash_set('success', admin_t('admin.user.unbanned'));
        }
        redirect(site_base_admin('user/list'));
    }

    /** 注销用户：禁止登录，前台历史内容作者匿名展示为“用户已注销”（数据不删除） */
    public static function deregisterAction()
    {
        Auth::require_cap('manage_users');
        $user = self::targetUser(input_int('id', 0, 'post'));
        if (!$user) {
            flash_set('error', admin_t('admin.user.not_found'));
            redirect(site_base_admin('user/list'));
        }
        $id = (int) $user['id'];
        if ($id === Auth::id()) {
            flash_set('error', admin_t('admin.user.self_deregister'));
            redirect(site_base_admin('user/list'));
        }
        if ($id === self::rootAdminId()) {
            flash_set('error', admin_t('admin.user.root_deregister'));
            redirect(site_base_admin('user/list'));
        }
        if ($user['role'] === 'admin' && self::adminCount() <= 1) {
            flash_set('error', admin_t('admin.user.last_admin_avail'));
            redirect(site_base_admin('user/list'));
        }
        DB::update('users', array('is_deleted' => 1), array('id' => $id));
        blog_log('user', 'user.deregister', 'success', array('target_user_id' => $id));
        flash_set('success', admin_t('admin.user.deregistered'));
        redirect(site_base_admin('user/list'));
    }

    /** 恢复注销：撤销匿名展示并允许重新登录 */
    public static function restoreAction()
    {
        Auth::require_cap('manage_users');
        $id = input_int('id', 0, 'post');
        if ($id > 0) {
            DB::update('users', array('is_deleted' => 0), array('id' => $id));
            blog_log('user', 'user.restore', 'success', array('target_user_id' => $id));
            flash_set('success', admin_t('admin.user.restored'));
        }
        redirect(site_base_admin('user/list'));
    }

    /** 按 ID 取目标用户行（不存在返回 null） */
    private static function targetUser($id)
    {
        if ((int) $id <= 0) {
            return null;
        }
        return DB::query('users')->where('id', '=', (int) $id)->first();
    }

    /**
     * 可用管理员数量（role=admin 且未被禁用/封禁/注销，保护最后一位）
     * 注意：调用方在“检查-更新”之间存在 TOCTOU 竞态窗口，待 DB 层事务支持后收严
     */
    private static function adminCount()
    {
        return DB::query('users')
            ->where('role', '=', 'admin')
            ->where('status', '=', 1)
            ->where('is_banned', '=', 0)
            ->where('is_deleted', '=', 0)
            ->count();
    }

    /** 安装管理员：安装程序创建的首位管理员（id 最小的 admin） */
    private static function rootAdminId()
    {
        $rows = DB::query('users')
            ->where('role', '=', 'admin')
            ->orderBy('id', 'ASC')
            ->limit(1)
            ->select();
        return $rows ? (int) $rows[0]['id'] : 0;
    }

    /**
     * 生成满足口令复杂度策略的随机初始密码
     *
     * @return string
     */
    private static function generateStrongPassword()
    {
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghijkmnpqrstuvwxyz';
        $digit = '23456789';
        $symbol = '!@#$%^&*';
        $all = $upper . $lower . $digit . $symbol;
        $pwd = $upper[random_int(0, strlen($upper) - 1)]
            . $lower[random_int(0, strlen($lower) - 1)]
            . $digit[random_int(0, strlen($digit) - 1)]
            . $symbol[random_int(0, strlen($symbol) - 1)];
        for ($i = 4; $i < 14; $i++) {
            $pwd .= $all[random_int(0, strlen($all) - 1)];
        }
        return str_shuffle($pwd);
    }
}
