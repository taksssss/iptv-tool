const ThemeApp = {
    data() {
        return {
            theme: localStorage.getItem('theme') || ''
        };
    },
    computed: {
        iconClass() {
            const themeToUse = this.currentTheme();
            return `fas ${themeToUse === 'dark' ? 'fa-moon' : themeToUse === 'light' ? 'fa-sun' : 'fa-adjust'}`;
        },
        labelText() {
            const themeToUse = this.currentTheme();
            return themeToUse === 'dark' ? 'Dark' : themeToUse === 'light' ? 'Light' : 'Auto';
        }
    },
    mounted() {
        this.applyTheme(this.currentTheme());
        // Listen to system theme changes only when theme is auto
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            if (!localStorage.getItem('theme')) {
                const systemTheme = e.matches ? 'dark' : 'light';
                this.applyTheme(systemTheme);
            }
        });
    },
    methods: {
        currentTheme() {
            if (this.theme === '') {
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                return prefersDark ? 'dark' : 'light';
            }
            return this.theme;
        },
        applyTheme(theme) {
            document.body.classList.add('theme-transition');
            document.body.classList.remove('dark', 'light');
            document.body.classList.add(theme);
        },
        toggleTheme() {
            const newTheme = this.theme === 'light' ? 'dark' : (this.theme === 'dark' ? '' : 'light');
            this.theme = newTheme;
            localStorage.setItem('theme', newTheme);
            this.applyTheme(this.currentTheme());
        }
    }
};

Vue.createApp(ThemeApp).mount('#theme-app');

