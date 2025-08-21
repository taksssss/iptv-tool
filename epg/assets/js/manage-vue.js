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
                channel_mappings: ''
            },
            loading: false
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
            // Trigger original form submission logic
            const formEl = document.getElementById('settingsForm');
            formEl.dispatchEvent(new Event('submit', { cancelable: true }));
        }
    }
};

Vue.createApp(ManageApp).mount('#manage-app');

