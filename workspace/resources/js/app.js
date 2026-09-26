import Alpine from 'alpinejs';

const SIDEBAR_COLLAPSED_KEY = 'wesal_ws_sidebar_collapsed';

/**
 * هيكل الصفحات: القائمة الجانبية (تصغير/توسيع يُحفظ في المتصفح، ودرج منزلق
 * على الجوال). كل تبويب صفحة مستقلة بمساره، فلا حالة تبويبات هنا.
 */
Alpine.data('appShell', () => ({
    collapsed: document.documentElement.classList.contains('sb-collapsed'),
    mobileOpen: false,

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
}));

/**
 * لوحة الكانبان. السحب والإفلات تحسين فوق نماذج «نقل إلى» العادية لا بديل
 * عنها: كل نقل يمر على نفس المسار (tasks.move) وصلاحياته، ومن لا يستطيع
 * السحب (لوحة مفاتيح، قارئ شاشة، لمس) يستخدم النموذج.
 */
Alpine.data('kanban', () => ({
    dragging: null,
    over: null,
    message: '',
    error: '',

    start(event, id) {
        this.dragging = id;
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(id));
    },

    drop(event, status) {
        this.over = null;
        const card = document.getElementById(`task-${this.dragging ?? event.dataTransfer.getData('text/plain')}`);
        this.dragging = null;
        const list = event.currentTarget.querySelector('[data-cards]');

        if (!card || !list) {
            return;
        }

        const before = [...list.querySelectorAll('[data-task]')].find(
            (element) => element !== card && event.clientY < element.getBoundingClientRect().top + element.offsetHeight / 2,
        );

        this.move(card, list, before ?? null, status);
    },

    submitMove(event) {
        const card = event.target.closest('[data-task]');
        const status = event.target.querySelector('select[name=status]').value;
        const list = document.querySelector(`[data-column="${status}"] [data-cards]`);

        this.move(card, list, null, status);
    },

    async move(card, list, before, status) {
        const origin = { parent: card.parentElement, next: card.nextElementSibling };
        list.insertBefore(card, before);
        const position = [...list.querySelectorAll('[data-task]')].indexOf(card);
        this.error = '';

        try {
            const response = await fetch(card.dataset.moveUrl, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ status, position }),
            });

            if (response.status === 401 || response.status === 419) {
                // انتهت الجلسة (الخروج التلقائي): idleTimeout يحوّل إلى صفحة الدخول بسببها
                window.dispatchEvent(new CustomEvent('session-expired'));
                return;
            }

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            this.message = (await response.json()).message;
            const select = card.querySelector('select[name=status]');

            if (select) {
                select.value = status;
            }
        } catch {
            origin.parent.insertBefore(card, origin.next);
            this.error = 'تعذّر نقل المهمة. ربما تغيّرت صلاحيتك أو توقف المشروع؛ حدّث الصفحة.';
        }

        this.recount();
    },

    recount() {
        document.querySelectorAll('[data-column]').forEach((column) => {
            column.querySelector('[data-count]').textContent = column.querySelectorAll('[data-task]').length;
        });
    },
}));

/**
 * محرر بنود الفاتورة وأمر الشراء. المجاميع هنا معاينة فقط بنفس حساب الخادم
 * (بالهللات أعداداً صحيحة، تقريب نصف للأعلى)؛ الخادم يعيد الحساب ويخزّنه.
 */
const toHalalas = (value) => Math.round((parseFloat(value) || 0) * 100);
const money = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

Alpine.data('lineItems', (initial, vatRate, errors) => ({
    items: initial.length ? initial : [{ description: '', quantity: '1', unit_price: '' }],
    vatRate: parseFloat(vatRate),
    errors,

    add() {
        this.items.push({ description: '', quantity: '1', unit_price: '' });
        this.$nextTick(() => this.$root.querySelector(`#item-${this.items.length - 1}-description`)?.focus());
    },

    remove(index) {
        if (this.items.length > 1) {
            this.items.splice(index, 1);
        }
    },

    error(index, field) {
        return (this.errors[`items.${index}.${field}`] ?? [])[0] ?? '';
    },

    lineHalalas(item) {
        return Math.floor((toHalalas(item.quantity) * toHalalas(item.unit_price) + 50) / 100);
    },

    get subtotal() {
        return this.items.reduce((sum, item) => sum + this.lineHalalas(item), 0);
    },

    get vat() {
        return Math.floor((this.subtotal * Math.round(this.vatRate * 100) + 5000) / 10000);
    },

    format(halalas) {
        return `${money.format(halalas / 100)} ر.س`;
    },
}));

const IDLE_ACTIVITY_KEY = 'wesal_ws_idle_at';
const SIGNED_OUT_KEY = 'wesal_ws_signed_out';

/**
 * الخروج التلقائي بعد الخمول. أي حركة (الفأرة، اللمس، لوحة المفاتيح، الكتابة)
 * نشاط يُشارَك بين التبويبات، ويصل الخادم نبضةً مرة في الدقيقة على الأكثر فلا
 * تنتهي جلسة من يكتب نصاً طويلاً دون أن يرسل شيئاً. قبل الخروج بدقيقتين تنبيه
 * «هل ما زلت هنا؟» (WCAG 2.2.1: تنبيه وتمديد بفعل بسيط). الخادم يفرض المهلة
 * نفسها (EnforceSessionTimeouts)، والحساب هنا بالوقت الفعلي لا بعدّ الثواني:
 * المتصفح يبطئ مؤقتات التبويب الخلفي ويوقفها أثناء نوم الجهاز.
 */
Alpine.data('idleTimeout', (config) => ({
    open: false,
    remaining: '',
    announcement: '',
    last: 0,
    beatAt: 0,
    ending: false,
    returnFocus: null,

    init() {
        // تحميل الصفحة طلب جدّد الجلسة على الخادم، فهو نشاط ونبضة معاً
        this.touch();
        this.beatAt = Date.now();

        const options = { capture: true, passive: true };
        ['pointermove', 'pointerdown', 'keydown', 'wheel', 'touchstart', 'touchmove', 'input', 'focusin'].forEach((type) =>
            document.addEventListener(type, (event) => this.activity(event), options),
        );

        // الخروج اليدوي من تبويب يُخرج البقية
        const logoutPath = new URL(config.logoutUrl, window.location.href).pathname;
        document.addEventListener('submit', (event) => {
            if (new URL(event.target.action, window.location.href).pathname === logoutPath) {
                this.signal();
            }
        }, true);

        window.addEventListener('storage', (event) => {
            if (event.key === SIGNED_OUT_KEY && event.newValue && !this.ending) {
                // مهلة قصيرة حتى يسبق طلبُ الخروج في التبويب الآخر فتحَ صفحة الدخول هنا
                this.ending = true;
                setTimeout(() => window.location.replace(config.loginUrl), 1000);
            } else if (event.key === IDLE_ACTIVITY_KEY) {
                this.tick();
            }
        });
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.tick();
            }
        });
        window.addEventListener('session-expired', () => this.leave());

        setInterval(() => this.tick(), 1000);
    },

    lastActivity() {
        try {
            return Math.max(this.last, Number(localStorage.getItem(IDLE_ACTIVITY_KEY)) || 0);
        } catch (e) {
            // التخزين قد يكون محجوباً (تصفح خاص) — العدّ يبقى لهذا التبويب وحده
            return this.last;
        }
    },

    touch() {
        this.last = Date.now();
        try {
            localStorage.setItem(IDLE_ACTIVITY_KEY, String(this.last));
        } catch (e) {
            // كما في lastActivity()
        }
    },

    activity(event) {
        if (this.ending) {
            return;
        }
        if (this.open) {
            // التركيز ينتقل برمجياً إلى زر البقاء حين يظهر التنبيه، فلا يُعدّ حضوراً
            if (event.type !== 'focusin') {
                this.stay();
            }
            return;
        }
        if (Date.now() - this.last < 5000) {
            return;
        }
        this.touch();
        if (Date.now() - this.beatAt >= 60000) {
            this.heartbeat();
        }
    },

    tick() {
        if (this.ending) {
            return;
        }
        const left = config.minutes * 60000 - (Date.now() - this.lastActivity());
        if (left <= 0) {
            this.expire();
        } else if (left <= 120000) {
            this.warn(left);
        } else if (this.open) {
            this.close();
        }
    },

    warn(left) {
        const seconds = Math.max(0, Math.ceil(left / 1000));
        this.remaining = `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
        if (this.open) {
            if (seconds <= 30 && !this.announcement) {
                this.announcement = 'بقيت 30 ثانية على تسجيل خروجك.';
            }
            return;
        }
        this.open = true;
        this.announcement = '';
        this.returnFocus = document.activeElement;
        // x-show يُظهر العنصر في إطار الرسم التالي لا فوراً، والتركيز على عنصر مخفي لا يحدث
        this.$nextTick(() => requestAnimationFrame(() => this.$refs.stay?.focus({ preventScroll: true })));
    },

    close() {
        this.open = false;
        this.announcement = '';
        const target = this.returnFocus;
        this.returnFocus = null;
        if (target?.focus && document.contains(target)) {
            target.focus({ preventScroll: true });
        }
    },

    stay() {
        this.touch();
        this.close();
        this.heartbeat();
    },

    async heartbeat() {
        this.beatAt = Date.now();
        try {
            const response = await fetch(config.heartbeatUrl, { method: 'POST', headers: this.headers() });
            if (response.status === 401 || response.status === 419) {
                this.leave();
            }
        } catch {
            // انقطاع مؤقت: الخادم يحكم عند الطلب التالي
        }
    },

    async expire() {
        this.ending = true;
        // ننتظر الخادم قبل فتح صفحة الدخول، وإلا ردّتنا إلى اللوحة والجلسة قائمة
        await Promise.race([
            fetch(config.timeoutUrl, { method: 'POST', headers: this.headers(), keepalive: true }).catch(() => null),
            new Promise((resolve) => setTimeout(resolve, 3000)),
        ]);
        this.leave();
    },

    leave() {
        this.ending = true;
        this.signal();
        window.location.replace(`${config.loginUrl}?timeout=1`);
    },

    signal() {
        try {
            localStorage.setItem(SIGNED_OUT_KEY, String(Date.now()));
        } catch (e) {
            // بلا تخزين تكتشف التبويبات الأخرى الخروج عند طلبها التالي
        }
    },

    headers() {
        return {
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        };
    },
}));

window.Alpine = Alpine;

Alpine.start();
