// 认证状态管理
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { authApi, type ChangePasswordParams } from '@/api/auth'

export const useAuthStore = defineStore('auth', () => {
  const isLoggedIn = ref(false)
  const loading = ref(false)

  // 检查登录状态
  async function checkLoginStatus() {
    try {
      const response = await authApi.checkLoginStatus()
      isLoggedIn.value = response.loggedin
      return response.loggedin
    } catch (error) {
      isLoggedIn.value = false
      return false
    }
  }

  // 登录
  async function login(password: string) {
    loading.value = true
    try {
      const response = await authApi.login({ password })
      if (response.success) {
        isLoggedIn.value = true
      }
      return response
    } finally {
      loading.value = false
    }
  }

  // 修改密码
  async function changePassword(params: ChangePasswordParams) {
    loading.value = true
    try {
      const response = await authApi.changePassword(params)
      return response
    } finally {
      loading.value = false
    }
  }

  // 退出登录
  async function logout() {
    loading.value = true
    try {
      await authApi.logout()
      isLoggedIn.value = false
    } finally {
      loading.value = false
    }
  }

  return {
    isLoggedIn,
    loading,
    checkLoginStatus,
    login,
    changePassword,
    logout
  }
})
