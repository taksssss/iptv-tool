import { ref } from 'vue'

export function useTheme() {
  const theme = ref(localStorage.getItem('theme') || 'auto')

  const applyTheme = (newTheme) => {
    document.body.classList.remove('light', 'dark')
    
    if (newTheme === 'auto') {
      const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
      document.body.classList.add(prefersDark ? 'dark' : 'light')
    } else {
      document.body.classList.add(newTheme)
    }
    
    localStorage.setItem('theme', newTheme)
    theme.value = newTheme
  }

  const setTheme = (newTheme) => {
    applyTheme(newTheme)
  }

  // Apply theme on init
  applyTheme(theme.value)

  return {
    theme,
    setTheme
  }
}
