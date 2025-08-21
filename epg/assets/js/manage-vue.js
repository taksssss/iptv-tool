const ManageApp = {
    data() {
        return {
            form: {
                xml_urls: '',
                days_to_keep: 7,
                start_time: '00:00',
                end_time: '23:59',
                interval_hour: 0,
                interval_minute: 0,
                channel_mappings: '',
                // more settings
                gen_xml: 1,
                include_future_only: 1,
                ret_default: 1,
                token_range: 0,
                user_agent_range: 0,
                cht_to_chs: 1,
                db_type: 'sqlite',
                cached_type: 'memcached',
                target_time_zone: '0',
                ip_list_mode: 0,
                check_update: 1,
                debug_mode: 0,
                gen_list_enable: 0,
                // live settings
                live_template_enable: 0,
                live_fuzzy_match: 0,
                live_url_comment: 0,
                check_ipv6: 0,
                min_resolution_width: 0,
                min_resolution_height: 0,
                urls_limit: 0,
                sort_by_delay: 1,
                check_speed_auto_sync: 0,
                check_speed_interval_factor: 1,
                live_source_auto_sync: 0,
                live_channel_name_process: 1,
                gen_live_update_time: 1,
                m3u_icon_first: 0,
                live_tvg_logo_enable: 1,
                live_tvg_id_enable: 1,
                live_tvg_name_enable: 1,
                // mysql/redis
                mysql_host: '',
                mysql_dbname: '',
                mysql_username: '',
                mysql_password: '',
                redis_host: '',
                redis_port: '',
                redis_password: ''
            },
            loading: false,
            token: '',
            user_agent: '',
            server_url: '',
            mod_rewrite: false,
            timezones: [
                { value: '0', label: '关闭' },
                { value: '+0800', label: 'UTC+08:00' },
                { value: '-1200', label: 'UTC-12:00' },
                { value: '-1100', label: 'UTC-11:00' },
                { value: '-1000', label: 'UTC-10:00' },
                { value: '-0900', label: 'UTC-09:00' },
                { value: '-0800', label: 'UTC-08:00' },
                { value: '-0700', label: 'UTC-07:00' },
                { value: '-0600', label: 'UTC-06:00' },
                { value: '-0500', label: 'UTC-05:00' },
                { value: '-0400', label: 'UTC-04:00' },
                { value: '-0300', label: 'UTC-03:00' },
                { value: '-0200', label: 'UTC-02:00' },
                { value: '-0100', label: 'UTC-01:00' },
                { value: '+0000', label: 'UTC+00:00' },
                { value: '+0100', label: 'UTC+01:00' },
                { value: '+0200', label: 'UTC+02:00' },
                { value: '+0300', label: 'UTC+03:00' },
                { value: '+0400', label: 'UTC+04:00' },
                { value: '+0500', label: 'UTC+05:00' },
                { value: '+0600', label: 'UTC+06:00' },
                { value: '+0700', label: 'UTC+07:00' },
                { value: '+0900', label: 'UTC+09:00' },
                { value: '+1000', label: 'UTC+10:00' },
                { value: '+1100', label: 'UTC+11:00' },
                { value: '+1200', label: 'UTC+12:00' }
            ]
        };
    },
    created() {
        this.fetchConfig();
    },
    methods: {
        async fetchConfig() {
            this.loading = true;
            try {
                const res = await fetch('manage.php?get_config=true');
                const cfg = await res.json();
                this.form.xml_urls = (cfg.xml_urls || []).map(v => (v || '').trim()).join('\n');
                this.form.days_to_keep = Number(cfg.days_to_keep || 7);
                this.form.start_time = cfg.start_time || '00:00';
                this.form.end_time = cfg.end_time || '23:59';
                const hour = Math.floor((cfg.interval_time || 0) / 3600);
                const minute = Math.floor(((cfg.interval_time || 0) % 3600) / 60);
                this.form.interval_hour = hour;
                this.form.interval_minute = minute;
                const mappings = cfg.channel_mappings || {};
                this.form.channel_mappings = Object.keys(mappings).map(k => `${k} => ${mappings[k]}`).join('\n');
                // assign extended fields if present
                const defaults = this.form;
                const keys = ['gen_xml','include_future_only','ret_default','token_range','user_agent_range','cht_to_chs','db_type','cached_type','target_time_zone','ip_list_mode','check_update','debug_mode','gen_list_enable'];
                keys.forEach(k => { if (cfg[k] !== undefined) defaults[k] = cfg[k]; });
                // live settings
                const liveKeys = ['live_template_enable','live_fuzzy_match','live_url_comment','check_ipv6','min_resolution_width','min_resolution_height','urls_limit','sort_by_delay','check_speed_auto_sync','check_speed_interval_factor','live_source_auto_sync','live_channel_name_process','gen_live_update_time','m3u_icon_first','live_tvg_logo_enable','live_tvg_id_enable','live_tvg_name_enable'];
                liveKeys.forEach(k => { if (cfg[k] !== undefined) defaults[k] = cfg[k]; });
                // mysql/redis
                if (cfg.mysql) {
                    defaults.mysql_host = cfg.mysql.host || '';
                    defaults.mysql_dbname = cfg.mysql.dbname || '';
                    defaults.mysql_username = cfg.mysql.username || '';
                    defaults.mysql_password = cfg.mysql.password || '';
                }
                if (cfg.redis) {
                    defaults.redis_host = cfg.redis.host || '';
                    defaults.redis_port = cfg.redis.port || '';
                    defaults.redis_password = cfg.redis.password || '';
                }
                // auth token and ua
                this.token = cfg.token || '';
                this.user_agent = cfg.user_agent || '';
                this.server_url = cfg.server_url || '';
                this.mod_rewrite = !!cfg.mod_rewrite;
            } catch (e) {
                console.error('fetchConfig failed', e);
            } finally {
                this.loading = false;
            }
        },
        submitSettings() {
            // Keep existing submit handler in manage.js working by preserving element values
            document.getElementById('xml_urls').value = this.form.xml_urls;
            document.getElementById('days_to_keep').value = String(this.form.days_to_keep);
            document.getElementById('start_time').value = this.form.start_time;
            document.getElementById('end_time').value = this.form.end_time;
            document.getElementById('interval_hour').value = String(this.form.interval_hour);
            document.getElementById('interval_minute').value = String(this.form.interval_minute);
            document.getElementById('channel_mappings').value = this.form.channel_mappings;
            // sync extended fields if present in DOM
            const syncIds = ['gen_xml','include_future_only','ret_default','token_range','user_agent_range','cht_to_chs','db_type','cached_type','target_time_zone','ip_list_mode','check_update','debug_mode','gen_list_enable'];
            syncIds.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = String(this.form[id]);
            });
            // Trigger original form submission logic
            const formEl = document.getElementById('settingsForm');
            formEl.dispatchEvent(new Event('submit', { cancelable: true }));
        },
        onShowLiveUrl() {
            if (typeof showLiveUrl === 'function') {
                showLiveUrl(String(this.token).split('\n')[0], this.server_url, String(this.form.token_range), this.mod_rewrite);
            }
        },
        onTokenRangeChange() {
            if (typeof showTokenRangeMessage === 'function') {
                showTokenRangeMessage(String(this.token), this.server_url, this.mod_rewrite);
            }
        },
        onChangeTokenUA(type) {
            if (typeof changeTokenUA === 'function') {
                const value = type === 'token' ? String(this.token) : String(this.user_agent);
                changeTokenUA(type, value);
            }
        }
    }
};

Vue.createApp(ManageApp).mount('#manage-app');