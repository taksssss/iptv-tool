const LoginApp = {
    data() {
        return {
            password: '',
            oldPassword: '',
            newPassword: '',
            showChangeModal: false,
            errorMessage: '',
            successMessage: '',
            changeErrorMessage: ''
        };
    },
    methods: {
        async submitLogin() {
            this.errorMessage = '';
            try {
                const formData = new FormData();
                formData.append('login', '1');
                formData.append('login_ajax', '1');
                formData.append('password', this.password);
                const res = await fetch('manage.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    window.location.href = 'manage.php';
                } else {
                    this.errorMessage = data.message || '登录失败';
                }
            } catch (e) {
                this.errorMessage = '网络错误，请重试';
            }
        },
        openChangePassword() {
            this.showChangeModal = true;
            window.addEventListener('mousedown', this.handleOutsideClick);
        },
        closeChangePassword() {
            this.showChangeModal = false;
            window.removeEventListener('mousedown', this.handleOutsideClick);
        },
        handleOutsideClick(e) {
            const modal = document.getElementById('changePasswordModal');
            if (e.target === modal) {
                this.closeChangePassword();
            }
        },
        async submitChangePassword() {
            this.successMessage = '';
            this.changeErrorMessage = '';
            try {
                const formData = new FormData();
                formData.append('change_password', '1');
                formData.append('change_password_ajax', '1');
                formData.append('old_password', this.oldPassword);
                formData.append('new_password', this.newPassword);
                const res = await fetch('manage.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    this.successMessage = data.message || '密码已更改';
                    this.closeChangePassword();
                } else {
                    this.changeErrorMessage = data.message || '修改失败';
                }
            } catch (e) {
                this.changeErrorMessage = '网络错误，请重试';
            }
        }
    }
};

Vue.createApp(LoginApp).mount('#login-app');