import { defineStore } from 'pinia'
import { ref } from 'vue'
import { authApi } from '@/api/auth'

export const useAuthStore = defineStore('auth', () => {
  const isLoggedIn = ref(false)
  const loading = ref(false)

  async function checkLoginStatus() {
    try {
      const response = await authApi.checkLoginStatus()
      isLoggedIn.value = response.loggedin || false
      return isLoggedIn.value
    } catch (error) {
      isLoggedIn.value = false
      return false
    }
  }

  async function login(password) {
    loading.value = true
    try {
      const response = await authApi.login(password)
      if (response.success) {
        isLoggedIn.value = true
      }
      return response
    } finally {
      loading.value = false
    }
  }

  async function changePassword(params) {
    loading.value = true
    try {
      const response = await authApi.changePassword(params)
      return response
    } finally {
      loading.value = false
    }
  }

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
