// 认证相关 API
import apiClient from './index'

export interface LoginParams {
  password: string
}

export interface ChangePasswordParams {
  old_password?: string
  new_password: string
}

export interface AuthResponse {
  success: boolean
  message?: string
}

export interface LoginStatusResponse {
  loggedin: boolean
}

export const authApi = {
  // 登录
  login: (params: LoginParams): Promise<AuthResponse> => {
    const formData = new FormData()
    formData.append('action', 'login')
    formData.append('password', params.password)
    return apiClient.post('/auth.php', formData)
  },

  // 检查登录状态
  checkLoginStatus: (): Promise<LoginStatusResponse> => {
    return apiClient.get('/auth.php')
  },

  // 修改密码
  changePassword: (params: ChangePasswordParams): Promise<AuthResponse> => {
    const formData = new FormData()
    formData.append('action', 'change_password')
    if (params.old_password) {
      formData.append('old_password', params.old_password)
    }
    formData.append('new_password', params.new_password)
    return apiClient.post('/auth.php', formData)
  },

  // 退出登录
  logout: (): Promise<AuthResponse> => {
    const formData = new FormData()
    formData.append('action', 'logout')
    return apiClient.post('/auth.php', formData)
  }
}
