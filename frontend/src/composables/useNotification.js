import { ElMessage, ElNotification } from 'element-plus'

export function useNotification() {
  const success = (message, options = {}) => {
    ElMessage.success({
      message,
      duration: 3000,
      ...options
    })
  }

  const error = (message, options = {}) => {
    ElMessage.error({
      message,
      duration: 3000,
      ...options
    })
  }

  const warning = (message, options = {}) => {
    ElMessage.warning({
      message,
      duration: 3000,
      ...options
    })
  }

  const info = (message, options = {}) => {
    ElMessage.info({
      message,
      duration: 3000,
      ...options
    })
  }

  const notify = (options) => {
    ElNotification(options)
  }

  return {
    success,
    error,
    warning,
    info,
    notify
  }
}
