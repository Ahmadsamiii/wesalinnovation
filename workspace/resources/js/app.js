import Alpine from 'alpinejs';

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

window.Alpine = Alpine;

Alpine.start();
