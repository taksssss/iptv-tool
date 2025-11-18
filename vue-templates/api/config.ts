// 配置管理 API
import apiClient from './index'

export interface Config {
  xml_urls: string[]
  channel_mappings: Record<string, string>
  channel_bind_epg: Record<string, string>
  days_to_keep: number
  start_time: string
  end_time: string
  interval_time: number
  token: string
  token_md5?: string
  user_agent: string
  token_range: number
  user_agent_range: number
  gen_xml: boolean
  include_future_only: boolean
  ret_default: boolean
  cht_to_chs: boolean
  db_type: 'sqlite' | 'mysql'
  mysql: {
    host: string
    dbname: string
    username: string
    password: string
  }
  cached_type: 'memcached' | 'redis' | 'none'
  gen_list_enable: boolean
  check_update: boolean
  notify: boolean
  debug_mode: number
  target_time_zone: number | string
  ip_list_mode: number
  // 直播源相关配置
  live_source_config: string
  live_template_enable: boolean
  live_fuzzy_match: boolean
  live_url_comment: boolean
  live_tvg_logo_enable: boolean
  live_tvg_id_enable: boolean
  live_tvg_name_enable: boolean
  live_source_auto_sync: boolean
  live_channel_name_process: boolean
  gen_live_update_time: boolean
  m3u_icon_first: boolean
  ku9_secondary_grouping: boolean
  check_ipv6: boolean
  min_resolution_width: number
  min_resolution_height: number
  urls_limit: number
  sort_by_delay: boolean
  check_speed_auto_sync: boolean
  check_speed_interval_factor: number
  channel_ignore_chars: string
}

export interface UpdateConfigResponse {
  success: boolean
  message?: string
  db_type_set?: boolean
  interval_time?: number
  start_time?: string
  end_time?: string
}

export const configApi = {
  // 获取配置
  getConfig: (): Promise<Config> => {
    return apiClient.get('/config.php')
  },

  // 更新配置
  updateConfig: (config: Partial<Config>): Promise<UpdateConfigResponse> => {
    return apiClient.post('/config.php', config)
  },

  // 获取环境信息
  getEnv: (): Promise<{ server_url: string; redirect: boolean }> => {
    return apiClient.get('/config.php?action=get_env')
  }
}
