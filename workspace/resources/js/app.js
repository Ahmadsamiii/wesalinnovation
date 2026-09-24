import Alpine from 'alpinejs';

const SIDEBAR_COLLAPSED_KEY = 'wesal_ws_sidebar_collapsed';

/**
 * هيكل لوحة التحكم: القائمة الجانبية (تصغير/توسيع يُحفظ في المتصفح، ودرج
 * منزلق على الجوال) والتبويب النشط. التبويبات نفسها تأتي من config/roles.php
 * عبر initTabs، فلا تُعرَّف هنا أي قائمة أدوار.
 */
Alpine.data('appShell', () => ({
    collapsed: document.documentElement.classList.contains('sb-collapsed'),
    mobileOpen: false,
    tabs: {},
    tab: null,

    toggleCollapsed() {
        this.collapsed = !this.collapsed;
        document.documentElement.classList.toggle('sb-collapsed', this.collapsed);
        try {
            localStorage.setItem(SIDEBAR_COLLAPSED_KEY, this.collapsed ? '1' : '0');
        } catch (e) {
            // التخزين قد يكون محجوباً (تصفح خاص) — الحالة تبقى للجلسة فقط
        }
    },

    openMobile() {
        this.mobileOpen = true;
        this.$nextTick(() => this.$refs.sidebar?.querySelector('a, button')?.focus());
    },

    closeMobile() {
        if (!this.mobileOpen) {
            return;
        }
        this.mobileOpen = false;
        this.$refs.menuButton?.focus({ preventScroll: true });
    },

    initTabs(tabs) {
        this.tabs = tabs;
        const fromHash = decodeURIComponent(window.location.hash.slice(1));
        this.tab = fromHash in tabs ? fromHash : Object.keys(tabs)[0] ?? null;
    },

    get hasTabs() {
        return Object.keys(this.tabs).length > 0;
    },

    get tabLabel() {
        return this.tabs[this.tab] ?? '';
    },

    setTab(key) {
        if (!(key in this.tabs)) {
            return;
        }
        this.tab = key;
        this.mobileOpen = false;
        history.replaceState(null, '', '#' + key);
    },
}));

window.Alpine = Alpine;

Alpine.start();
