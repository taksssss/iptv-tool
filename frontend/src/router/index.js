import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/Auth/Login.vue'),
    meta: { requiresAuth: false }
  },
  {
    path: '/',
    component: () => import('@/components/Layout/AppLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'Dashboard',
        component: () => import('@/views/Dashboard.vue')
      },
      // Config routes
      {
        path: 'config',
        name: 'Config',
        component: () => import('@/views/Config/Index.vue')
      },
      // EPG routes
      {
        path: 'epg',
        name: 'Epg',
        component: () => import('@/views/Epg/Index.vue')
      },
      {
        path: 'epg/channels',
        name: 'EpgChannels',
        component: () => import('@/views/Epg/ChannelList.vue')
      },
      {
        path: 'epg/channel-bind',
        name: 'EpgChannelBind',
        component: () => import('@/views/Epg/ChannelBind.vue')
      },
      {
        path: 'epg/generate-list',
        name: 'EpgGenerateList',
        component: () => import('@/views/Epg/GenerateList.vue')
      },
      // Live routes
      {
        path: 'live',
        name: 'Live',
        component: () => import('@/views/Live/Index.vue')
      },
      {
        path: 'live/source-config',
        name: 'LiveSourceConfig',
        component: () => import('@/views/Live/SourceConfig.vue')
      },
      {
        path: 'live/speed-test',
        name: 'LiveSpeedTest',
        component: () => import('@/views/Live/SpeedTest.vue')
      },
      {
        path: 'live/template',
        name: 'LiveTemplate',
        component: () => import('@/views/Live/Template.vue')
      },
      // Icon routes
      {
        path: 'icon',
        name: 'Icon',
        component: () => import('@/views/Icon/Index.vue')
      },
      {
        path: 'icon/upload',
        name: 'IconUpload',
        component: () => import('@/views/Icon/Upload.vue')
      },
      {
        path: 'icon/mapping',
        name: 'IconMapping',
        component: () => import('@/views/Icon/Mapping.vue')
      },
      // System routes
      {
        path: 'system/update-log',
        name: 'SystemUpdateLog',
        component: () => import('@/views/System/UpdateLog.vue')
      },
      {
        path: 'system/cron-log',
        name: 'SystemCronLog',
        component: () => import('@/views/System/CronLog.vue')
      },
      {
        path: 'system/access-log',
        name: 'SystemAccessLog',
        component: () => import('@/views/System/AccessLog.vue')
      },
      {
        path: 'system/database',
        name: 'SystemDatabase',
        component: () => import('@/views/System/Database.vue')
      },
      {
        path: 'system/file-manager',
        name: 'SystemFileManager',
        component: () => import('@/views/System/FileManager.vue')
      },
      // About routes
      {
        path: 'about/help',
        name: 'AboutHelp',
        component: () => import('@/views/About/Help.vue')
      },
      {
        path: 'about/version',
        name: 'AboutVersion',
        component: () => import('@/views/About/Version.vue')
      },
      {
        path: 'about/donation',
        name: 'AboutDonation',
        component: () => import('@/views/About/Donation.vue')
      }
    ]
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// Navigation guard
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()
  
  // Check if route requires authentication
  if (to.meta.requiresAuth !== false) {
    // Check login status
    if (!authStore.isLoggedIn) {
      const isLoggedIn = await authStore.checkLoginStatus()
      if (!isLoggedIn) {
        next({ name: 'Login', query: { redirect: to.fullPath } })
        return
      }
    }
  }
  
  next()
})

export default router
