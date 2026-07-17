<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AdminLayout from '../Layouts/AdminLayout.vue';

const page = usePage();
const today = new Date();
const sevenDaysAgo = new Date(today);
sevenDaysAgo.setDate(today.getDate() - 7);

const formatDate = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const filters = ref({
    startDate: formatDate(sevenDaysAgo),
    endDate: formatDate(today),
    country: '0',
});

const loading = ref(false);
const error = ref('');
const activeTab = ref('sales');
const conversionLoading = ref(false);
const conversionError = ref('');
const activeConversionTab = ref('general');
const visitsLoading = ref(false);
const visitsError = ref('');
const activeVisitsTab = ref('general');
const satisfactionLoading = ref(false);
const satisfactionError = ref('');
const activeSatisfactionStoreCountry = ref('');
const categoriesLoading = ref(false);
const categoriesError = ref('');
const activeCategoryCountry = ref('0');
const segmentsLoading = ref(false);
const segmentsError = ref('');
const paymentFormsLoading = ref(false);
const paymentFormsError = ref('');
const geographicLoading = ref(false);
const geographicError = ref('');
const appLoading = ref(false);
const appError = ref('');
const chartData = ref({
    categories: [],
    series: [],
    filters: {},
    notes: {},
});
const conversionData = ref({
    categories: [],
    series: [],
    rows: [],
    totals: {},
    filters: {},
});
const visitsData = ref({
    mode: 'general',
    categories: [],
    series: [],
    rows: [],
    totals: {},
    filters: {},
});
const salesTooltip = ref(null);
const hiddenSalesSeries = ref(new Set());
const salesChartSvg = ref(null);
const salesExportMenuOpen = ref(false);
const conversionChartSvg = ref(null);
const conversionExportMenuOpen = ref(false);
const conversionTooltip = ref(null);
const visitsChartSvg = ref(null);
const visitsExportMenuOpen = ref(false);
const visitsTooltip = ref(null);
const satisfactionData = ref({
    filters: {},
    legend: [],
    byCountry: [],
    byPaymentType: [],
    byCheckout: [],
    byStore: {
        countries: [],
        rows: [],
    },
});
const categoryData = ref({
    filters: {},
    countries: [
        { id: 0, label: 'Regional', country: 'REGIONAL' },
        { id: 1, label: 'El Salvador', country: 'El Salvador' },
        { id: 2, label: 'Guatemala', country: 'Guatemala' },
        { id: 3, label: 'Costa Rica', country: 'Costa Rica' },
        { id: 7, label: 'Honduras', country: 'Honduras' },
        { id: 8, label: 'Venezuela', country: 'Venezuela' },
    ],
    dates: [],
    categories: [],
    rows: [],
});
const categoryFilters = ref({
    startDate: formatDate(new Date(today.getFullYear(), today.getMonth(), today.getDate() - 3)),
    endDate: formatDate(today),
});
const segmentsData = ref({
    filters: {},
    segments: [],
    sales: {
        rows: [],
        totals: {
            byPaymentType: [],
            store: 0,
            delivery: 0,
            total: 0,
        },
    },
    averageTicket: {
        rows: [],
    },
});
const paymentFormsData = ref({
    filters: {},
    segments: [
        { key: 'sv_web', label: 'El Salvador Web' },
        { key: 'sv_app', label: 'El Salvador App' },
        { key: 'gt', label: 'Guatemala' },
        { key: 'cr', label: 'Costa Rica' },
        { key: 'hn', label: 'Honduras' },
        { key: 've', label: 'Venezuela' },
    ],
    issuers: ['VISA', 'MASTERCARD', 'AMEX', 'EFECTIVO'],
    store: [],
    delivery: [],
});
const geographicData = ref({
    filters: {},
    summary: {
        departments: 0,
        total: 0,
    },
    rows: [],
});
const appData = ref({
    filters: {
        year: today.getFullYear(),
    },
    years: [],
    platforms: [
        { key: 'android', label: 'Android', color: '#16a34a' },
        { key: 'ios', label: 'iOS', color: '#db2777' },
    ],
    summary: {
        android: 0,
        ios: 0,
        total: 0,
    },
    rows: [],
});
const appFilters = ref({
    year: today.getFullYear(),
});

const processedStatuses = [
    'PREPARADO',
    'EMPACADO-ENTREGA',
    'EN-RUTA',
    'ENTREGADO',
    'ANULADO-ERROR',
    'ANULADO-PRUEBA',
    'ANULADO-CLIENTE',
    'ANULADO-INVENTARIO',
    'DEVOLUCION',
    'ANULADO-EFECTIVO',
];

const user = computed(() => page.props.auth?.user || {});
const permissions = computed(() => page.props.auth?.permissions || []);
const hasPermission = (permission) =>
    permissions.value.includes(permission) || permissions.value.includes(`OP_${permission}`);
const storeCode = computed(() => String(user.value?.storeCode || user.value?.tiendas || '00000'));
const hasAssignedStore = computed(() => storeCode.value !== '' && storeCode.value !== '00000');
const canSeeAnalyticsDashboard = computed(() => hasPermission('INDICADORES_GENERICOS'));
const canSeeStoreDashboard = computed(() => hasPermission('INDICADORES_TIENDA'));
const isStoreManagerDashboard = computed(() => canSeeStoreDashboard.value && !canSeeAnalyticsDashboard.value);
const welcomeName = computed(() => user.value?.nombre || user.value?.usuario || 'Usuario');
const storeSummaryLoading = ref(false);
const storeSummaryError = ref('');
const storeSummary = ref({
    pending: { orders: 0, items: 0, amount: 0 },
    processed: { orders: 0, items: 0, amount: 0 },
    month: { orders: 0, items: 0, amount: 0 },
});

const baseCountries = [
    { id: '0', label: 'Todos' },
    { id: '1', label: 'El Salvador' },
    { id: '2', label: 'Guatemala' },
    { id: '3', label: 'Costa Rica' },
    { id: '7', label: 'Honduras' },
    { id: '8', label: 'Venezuela' },
    { id: '4', label: 'Consolidado' },
];

const dashboardCountryIds = [1, 2, 3, 7, 8];
const allowedCountryIds = computed(() =>
    (page.props.auth?.countries?.allowed || [])
        .map((country) => Number(country.id ?? country.countryId ?? country.country_id))
        .filter((countryId) => dashboardCountryIds.includes(countryId)),
);
const canSeeAllDashboardCountries = computed(() =>
    dashboardCountryIds.every((countryId) => allowedCountryIds.value.includes(countryId)),
);
const countries = computed(() => baseCountries.filter((country) => {
    if (country.id === '0') {
        return allowedCountryIds.value.length > 1;
    }

    if (country.id === '4') {
        return canSeeAllDashboardCountries.value;
    }

    return allowedCountryIds.value.includes(Number(country.id));
}));
const ensureValidSalesCountry = () => {
    if (countries.value.some((country) => country.id === filters.value.country)) {
        return;
    }

    filters.value.country = countries.value[0]?.id || '';
};

const tabs = [
    { id: 'sales', label: 'Venta' },
    { id: 'conversion', label: 'Conversion' },
    { id: 'satisfaction', label: 'Satisfaccion' },
    { id: 'categories', label: 'Categorias' },
    { id: 'segments', label: 'Segmentos' },
    { id: 'paymentForms', label: 'Formas de pago' },
    { id: 'geographic', label: 'Geografico' },
    { id: 'visits', label: 'Visitas' },
    { id: 'app', label: 'APP' },
];

const conversionTabs = [
    { id: 'general', label: 'General' },
    { id: 'sv', label: 'El Salvador' },
    { id: 'gt', label: 'Guatemala' },
    { id: 'cr', label: 'Costa Rica' },
    { id: 'hn', label: 'Honduras' },
    { id: 've', label: 'Venezuela' },
];
const visitsTabs = conversionTabs;

const palette = {
    sv_current: '#2f9f6b',
    sv_previous: '#8bd9b0',
    gt_current: '#dd5f61',
    gt_previous: '#f2a3a3',
    cr_current: '#2f83d0',
    cr_previous: '#8fc4f1',
    hn_current: '#d97706',
    hn_previous: '#fbbf24',
    ve_current: '#6d28d9',
    ve_previous: '#c4b5fd',
    total_current: '#7c3aed',
    total_previous: '#c4b5fd',
    conversion_current: '#2563eb',
    conversion_previous: '#93c5fd',
    visits_web: '#2563eb',
    visits_android: '#16a34a',
    visits_ios: '#db2777',
    app_android: '#16a34a',
    app_ios: '#db2777',
    visits_current: '#2563eb',
    visits_previous: '#93c5fd',
};

const salesSeries = computed(() => {
    if (filters.value.country === '0') {
        return chartData.value.series.filter((serie) => serie.countryId !== 0);
    }

    if (filters.value.country === '4') {
        return chartData.value.series.filter((serie) => serie.countryId === 0);
    }

    return chartData.value.series.filter((serie) => String(serie.countryId) === filters.value.country);
});
const visibleSeries = computed(() =>
    salesSeries.value.filter((serie) => !hiddenSalesSeries.value.has(serie.key)),
);

const maxValue = computed(() => Math.max(10, ...visibleSeries.value.flatMap((serie) => serie.data || [])));
const conversionMaxValue = computed(() => Math.max(5, ...conversionData.value.series.flatMap((serie) => serie.data || [])));
const visitsMaxValue = computed(() => Math.max(10, ...visitsData.value.series.flatMap((serie) => serie.data || [])));
const salesTooltipWidth = 190;
const salesTooltipHeight = 98;
const conversionTooltipWidth = 210;
const visitsTooltipWidth = 210;

const yTicks = computed(() => {
    const top = maxValue.value;
    return [top, top * 0.75, top * 0.5, top * 0.25, 0].map((value) => Math.round(value));
});
const conversionYTicks = computed(() => {
    const top = conversionMaxValue.value;
    return [top, top * 0.75, top * 0.5, top * 0.25, 0].map((value) => Number(value.toFixed(2)));
});
const visitsYTicks = computed(() => {
    const top = visitsMaxValue.value;
    return [top, top * 0.75, top * 0.5, top * 0.25, 0].map((value) => Math.round(value));
});

const totals = computed(() => visibleSeries.value.map((serie) => ({
    key: serie.key,
    label: serie.label,
    total: (serie.data || []).reduce((sum, value) => sum + Number(value || 0), 0),
})));

const conversionTotals = computed(() => conversionData.value.totals || {});
const visitsTotals = computed(() => visitsData.value.totals || {});
const satisfactionStoreCountries = computed(() => satisfactionData.value.byStore?.countries || []);
const satisfactionStoreRows = computed(() =>
    (satisfactionData.value.byStore?.rows || [])
        .filter((row) => row.country === activeSatisfactionStoreCountry.value),
);
const activeCategoryCountryName = computed(() =>
    (categoryData.value.countries || []).find((country) => String(country.id) === activeCategoryCountry.value)?.country || 'REGIONAL',
);
const categoryRows = computed(() =>
    (categoryData.value.rows || []).filter((row) => row.country === activeCategoryCountryName.value),
);
const categoryTotals = computed(() =>
    Object.values(categoryRows.value.reduce((carry, row) => {
        const key = row.category || 'N/D';
        carry[key] ??= {
            category: key,
            sale: 0,
        };
        carry[key].sale += Number(row.sale || 0);

        return carry;
    }, {}))
        .sort((a, b) => b.sale - a.sale),
);
const categoryMaxSale = computed(() => Math.max(1, ...categoryTotals.value.map((row) => row.sale)));
const segmentSalesRows = computed(() => segmentsData.value.sales?.rows || []);
const segmentTicketRows = computed(() => segmentsData.value.averageTicket?.rows || []);
const segmentTotals = computed(() => segmentsData.value.sales?.totals || { byPaymentType: [], store: 0, delivery: 0, total: 0 });
const paymentFormSegments = computed(() => paymentFormsData.value.segments || []);
const geographicRows = computed(() => geographicData.value.rows || []);
const geographicMaxValue = computed(() => Math.max(1, ...geographicRows.value.map((row) => Number(row.total || 0))));
const appRows = computed(() => appData.value.rows || []);
const appPlatforms = computed(() => appData.value.platforms || []);
const appMaxValue = computed(() =>
    Math.max(1, ...appRows.value.flatMap((row) => appPlatforms.value.map((platform) => Number(row[platform.key] || 0)))),
);

const chartWidth = 920;
const chartHeight = 320;
const padding = { top: 24, right: 28, bottom: 46, left: 64 };
const plotWidth = chartWidth - padding.left - padding.right;
const plotHeight = chartHeight - padding.top - padding.bottom;

const xFor = (index) => {
    const count = Math.max(chartData.value.categories.length - 1, 1);
    return padding.left + (index / count) * plotWidth;
};

const yFor = (value) => padding.top + plotHeight - (Number(value || 0) / maxValue.value) * plotHeight;
const conversionXFor = (index) => {
    const count = Math.max(conversionData.value.categories.length - 1, 1);
    return padding.left + (index / count) * plotWidth;
};
const conversionYFor = (value) => padding.top + plotHeight - (Number(value || 0) / conversionMaxValue.value) * plotHeight;
const visitsXFor = (index) => {
    const count = Math.max(visitsData.value.categories.length - 1, 1);
    return padding.left + (index / count) * plotWidth;
};
const visitsYFor = (value) => padding.top + plotHeight - (Number(value || 0) / visitsMaxValue.value) * plotHeight;

const linePath = (data) => (data || [])
    .map((value, index) => `${index === 0 ? 'M' : 'L'} ${xFor(index).toFixed(2)} ${yFor(value).toFixed(2)}`)
    .join(' ');
const conversionLinePath = (data) => (data || [])
    .map((value, index) => `${index === 0 ? 'M' : 'L'} ${conversionXFor(index).toFixed(2)} ${conversionYFor(value).toFixed(2)}`)
    .join(' ');
const visitsLinePath = (data) => (data || [])
    .map((value, index) => `${index === 0 ? 'M' : 'L'} ${visitsXFor(index).toFixed(2)} ${visitsYFor(value).toFixed(2)}`)
    .join(' ');

const money = (value) => new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 2,
}).format(Number(value || 0));
const amount = (value) => new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
}).format(Number(value || 0));
const integer = (value) => Number(value || 0).toLocaleString('en-US');

const salesTooltipX = computed(() => {
    if (!salesTooltip.value) {
        return 0;
    }

    return Math.min(
        chartWidth - padding.right - salesTooltipWidth,
        Math.max(padding.left, salesTooltip.value.x - salesTooltipWidth / 2),
    );
});
const salesTooltipY = computed(() => {
    if (!salesTooltip.value) {
        return 0;
    }

    return Math.max(6, salesTooltip.value.y - salesTooltipHeight - 12);
});
const showSalesTooltip = (serie, index, value) => {
    const yearMatch = String(serie.label || '').match(/\((\d{4})\)/);

    salesTooltip.value = {
        key: `${serie.key}-${index}`,
        x: xFor(index),
        y: yFor(value),
        color: palette[serie.key] || '#2563eb',
        date: chartData.value.categories[index] || '',
        country: serie.country || String(serie.label || '').replace(/\s*\(\d{4}\)\s*$/, '') || 'N/D',
        year: serie.year || yearMatch?.[1] || '',
        value,
    };
};
const hideSalesTooltip = () => {
    salesTooltip.value = null;
};
const salesSeriesIsHidden = (key) => hiddenSalesSeries.value.has(key);
const toggleSalesSeries = (key) => {
    const next = new Set(hiddenSalesSeries.value);

    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }

    hiddenSalesSeries.value = next;
    hideSalesTooltip();
};

const conversionTooltipHeight = computed(() => 34 + (conversionTooltip.value?.rows.length || 0) * 22);
const conversionTooltipX = computed(() => {
    if (!conversionTooltip.value) {
        return 0;
    }

    return Math.min(
        chartWidth - padding.right - conversionTooltipWidth,
        Math.max(padding.left, conversionTooltip.value.x - conversionTooltipWidth / 2),
    );
});
const conversionTooltipY = computed(() => {
    if (!conversionTooltip.value) {
        return 0;
    }

    return Math.max(6, conversionTooltip.value.y - conversionTooltipHeight.value - 12);
});
const showConversionTooltip = (index, value) => {
    const rows = conversionData.value.series.map((serie) => ({
        key: serie.key,
        label: serie.label,
        color: palette[serie.key] || '#2563eb',
        value: Number(serie.data?.[index] || 0),
    }));

    conversionTooltip.value = {
        key: `conversion-${index}`,
        x: conversionXFor(index),
        y: conversionYFor(value),
        date: conversionData.value.categories[index] || '',
        rows,
    };
};
const hideConversionTooltip = () => {
    conversionTooltip.value = null;
};

const visitsTooltipHeight = computed(() => 34 + (visitsTooltip.value?.rows.length || 0) * 22);
const visitsTooltipX = computed(() => {
    if (!visitsTooltip.value) {
        return 0;
    }

    return Math.min(
        chartWidth - padding.right - visitsTooltipWidth,
        Math.max(padding.left, visitsTooltip.value.x - visitsTooltipWidth / 2),
    );
});
const visitsTooltipY = computed(() => {
    if (!visitsTooltip.value) {
        return 0;
    }

    return Math.max(6, visitsTooltip.value.y - visitsTooltipHeight.value - 12);
});
const showVisitsTooltip = (index, value) => {
    const rows = visitsData.value.series.map((serie) => ({
        key: serie.key,
        label: serie.label,
        color: palette[serie.key] || '#2563eb',
        value: Number(serie.data?.[index] || 0),
    }));

    visitsTooltip.value = {
        key: `visits-${index}`,
        x: visitsXFor(index),
        y: visitsYFor(value),
        date: visitsData.value.categories[index] || '',
        rows,
    };
};
const hideVisitsTooltip = () => {
    visitsTooltip.value = null;
};

const salesExportFileName = (extension) =>
    `ventas-pais-${filters.value.startDate}-${filters.value.endDate}.${extension}`;

const csvCell = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`;

const downloadBlob = (blob, filename) => {
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.click();
    URL.revokeObjectURL(link.href);
};

const chartSvgMarkup = (svgElement) => {
    if (!svgElement) {
        return '';
    }

    const svg = svgElement.cloneNode(true);
    const style = document.createElementNS('http://www.w3.org/2000/svg', 'style');
    const rootStyles = getComputedStyle(document.documentElement);
    const surface = rootStyles.getPropertyValue('--stj-surface').trim() || '#111827';
    const text = rootStyles.getPropertyValue('--stj-text').trim() || '#f8fafc';
    const textSoft = rootStyles.getPropertyValue('--stj-text-soft').trim() || '#bfdbfe';
    const muted = rootStyles.getPropertyValue('--stj-muted').trim() || '#93c5fd';
    const border = rootStyles.getPropertyValue('--stj-border').trim() || '#334155';
    const borderSoft = rootStyles.getPropertyValue('--stj-border-soft').trim() || '#1f2937';

    style.textContent = `
        svg { background: ${surface}; }
        .fill-current { fill: currentColor; }
        .app-text { color: ${text}; }
        .app-text-soft { color: ${textSoft}; }
        .app-muted { color: ${muted}; }
        * { --stj-surface: ${surface}; --stj-text: ${text}; --stj-text-soft: ${textSoft}; --stj-muted: ${muted}; --stj-border: ${border}; --stj-border-soft: ${borderSoft}; }
    `;
    svg.insertBefore(style, svg.firstChild);
    svg.setAttribute('xmlns', 'http://www.w3.org/2000/svg');

    return new XMLSerializer().serializeToString(svg);
};

const salesChartSvgMarkup = () => chartSvgMarkup(salesChartSvg.value);

const exportSalesSvg = () => {
    const markup = salesChartSvgMarkup();

    if (!markup) {
        return;
    }

    downloadBlob(new Blob([markup], { type: 'image/svg+xml;charset=utf-8' }), salesExportFileName('svg'));
    salesExportMenuOpen.value = false;
};

const exportSalesPng = () => {
    const markup = salesChartSvgMarkup();

    if (!markup) {
        return;
    }

    const image = new Image();
    const svgUrl = URL.createObjectURL(new Blob([markup], { type: 'image/svg+xml;charset=utf-8' }));

    image.onload = () => {
        const scale = 2;
        const canvas = document.createElement('canvas');
        canvas.width = chartWidth * scale;
        canvas.height = chartHeight * scale;
        const context = canvas.getContext('2d');
        context.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--stj-surface').trim() || '#111827';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(image, 0, 0, canvas.width, canvas.height);
        URL.revokeObjectURL(svgUrl);
        canvas.toBlob((blob) => {
            if (blob) {
                downloadBlob(blob, salesExportFileName('png'));
            }
        }, 'image/png');
    };
    image.src = svgUrl;
    salesExportMenuOpen.value = false;
};

const exportSalesCsv = () => {
    if (!chartData.value.categories.length || !visibleSeries.value.length) {
        return;
    }

    const header = ['Fecha', ...visibleSeries.value.map((serie) => serie.label)];
    const rows = chartData.value.categories.map((date, index) => [
        date,
        ...visibleSeries.value.map((serie) => Number(serie.data?.[index] || 0).toFixed(2)),
    ]);
    const csv = [header, ...rows]
        .map((row) => row.map(csvCell).join(','))
        .join('\n');

    downloadBlob(new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8;' }), salesExportFileName('csv'));
    salesExportMenuOpen.value = false;
};

const conversionExportFileName = (extension) =>
    `conversion-${activeConversionTab.value}-${filters.value.startDate}-${filters.value.endDate}.${extension}`;

const conversionChartSvgMarkup = () => chartSvgMarkup(conversionChartSvg.value);

const exportConversionSvg = () => {
    const markup = conversionChartSvgMarkup();

    if (!markup) {
        return;
    }

    downloadBlob(new Blob([markup], { type: 'image/svg+xml;charset=utf-8' }), conversionExportFileName('svg'));
    conversionExportMenuOpen.value = false;
};

const exportConversionPng = () => {
    const markup = conversionChartSvgMarkup();

    if (!markup) {
        return;
    }

    const image = new Image();
    const svgUrl = URL.createObjectURL(new Blob([markup], { type: 'image/svg+xml;charset=utf-8' }));

    image.onload = () => {
        const scale = 2;
        const canvas = document.createElement('canvas');
        canvas.width = chartWidth * scale;
        canvas.height = chartHeight * scale;
        const context = canvas.getContext('2d');
        context.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--stj-surface').trim() || '#111827';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(image, 0, 0, canvas.width, canvas.height);
        URL.revokeObjectURL(svgUrl);
        canvas.toBlob((blob) => {
            if (blob) {
                downloadBlob(blob, conversionExportFileName('png'));
            }
        }, 'image/png');
    };
    image.src = svgUrl;
    conversionExportMenuOpen.value = false;
};

const exportConversionCsv = () => {
    if (!conversionData.value.categories.length || !conversionData.value.series.length) {
        return;
    }

    const header = ['Fecha', ...conversionData.value.series.map((serie) => serie.label)];
    const rows = conversionData.value.categories.map((date, index) => [
        date,
        ...conversionData.value.series.map((serie) => Number(serie.data?.[index] || 0).toFixed(2)),
    ]);
    const csv = [header, ...rows]
        .map((row) => row.map(csvCell).join(','))
        .join('\n');

    downloadBlob(new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8;' }), conversionExportFileName('csv'));
    conversionExportMenuOpen.value = false;
};

const visitsExportFileName = (extension) =>
    `visitas-${activeVisitsTab.value}-${filters.value.startDate}-${filters.value.endDate}.${extension}`;

const visitsChartSvgMarkup = () => chartSvgMarkup(visitsChartSvg.value);

const exportVisitsSvg = () => {
    const markup = visitsChartSvgMarkup();

    if (!markup) {
        return;
    }

    downloadBlob(new Blob([markup], { type: 'image/svg+xml;charset=utf-8' }), visitsExportFileName('svg'));
    visitsExportMenuOpen.value = false;
};

const exportVisitsPng = () => {
    const markup = visitsChartSvgMarkup();

    if (!markup) {
        return;
    }

    const image = new Image();
    const svgUrl = URL.createObjectURL(new Blob([markup], { type: 'image/svg+xml;charset=utf-8' }));

    image.onload = () => {
        const scale = 2;
        const canvas = document.createElement('canvas');
        canvas.width = chartWidth * scale;
        canvas.height = chartHeight * scale;
        const context = canvas.getContext('2d');
        context.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--stj-surface').trim() || '#111827';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(image, 0, 0, canvas.width, canvas.height);
        URL.revokeObjectURL(svgUrl);
        canvas.toBlob((blob) => {
            if (blob) {
                downloadBlob(blob, visitsExportFileName('png'));
            }
        }, 'image/png');
    };
    image.src = svgUrl;
    visitsExportMenuOpen.value = false;
};

const exportVisitsCsv = () => {
    if (!visitsData.value.categories.length || !visitsData.value.series.length) {
        return;
    }

    const header = ['Fecha', ...visitsData.value.series.map((serie) => serie.label)];
    const rows = visitsData.value.categories.map((date, index) => [
        date,
        ...visitsData.value.series.map((serie) => Number(serie.data?.[index] || 0)),
    ]);
    const csv = [header, ...rows]
        .map((row) => row.map(csvCell).join(','))
        .join('\n');

    downloadBlob(new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8;' }), visitsExportFileName('csv'));
    visitsExportMenuOpen.value = false;
};

const storeSummaryCards = computed(() => [
    {
        label: 'Pedidos pendientes',
        value: integer(storeSummary.value.pending.orders),
        detail: `${integer(storeSummary.value.pending.items)} articulos | ${money(storeSummary.value.pending.amount)}`,
    },
    {
        label: 'Pedidos procesados',
        value: integer(storeSummary.value.processed.orders),
        detail: `${integer(storeSummary.value.processed.items)} articulos | ${money(storeSummary.value.processed.amount)}`,
    },
    {
        label: 'Pedidos del mes',
        value: integer(storeSummary.value.month.orders),
        detail: `${integer(storeSummary.value.month.items)} articulos | ${money(storeSummary.value.month.amount)}`,
    },
]);

const monthStart = () => formatDate(new Date(today.getFullYear(), today.getMonth(), 1));

const loadStoreSummary = async () => {
    if (!user.value?.idPais || !hasAssignedStore.value) {
        return;
    }

    storeSummaryLoading.value = true;
    storeSummaryError.value = '';

    const params = {
        country: String(user.value.idPais),
        store: storeCode.value,
        startDate: monthStart(),
        endDate: formatDate(today),
    };

    try {
        const [pendingResponse, processedResponse] = await Promise.all([
            window.axios.get('/dashboard-api/sales/orders', {
                params: {
                    ...params,
                    pending: true,
                },
            }),
            window.axios.get('/dashboard-api/sales/orders', {
                params: {
                    ...params,
                    statuses: processedStatuses.join(','),
                },
            }),
        ]);
        const pending = pendingResponse.data.data?.summary || {};
        const processed = processedResponse.data.data?.summary || {};

        storeSummary.value = {
            pending,
            processed,
            month: {
                orders: Number(pending.orders || 0) + Number(processed.orders || 0),
                items: Number(pending.items || 0) + Number(processed.items || 0),
                amount: Number(pending.amount || 0) + Number(processed.amount || 0),
            },
        };
    } catch (exception) {
        storeSummaryError.value = exception.response?.data?.message || 'No fue posible cargar los indicadores de la tienda.';
    } finally {
        storeSummaryLoading.value = false;
    }
};

const loadChart = async () => {
    loading.value = true;
    error.value = '';
    hideSalesTooltip();

    try {
        const response = await window.axios.get('/dashboard-api/sales/regional-chart', {
            params: {
                startDate: filters.value.startDate,
                endDate: filters.value.endDate,
            },
        });

        chartData.value = response.data.data || chartData.value;
    } catch (exception) {
        error.value = exception.response?.data?.message || 'No fue posible cargar el grafico de ventas.';
    } finally {
        loading.value = false;
    }
};

const loadConversion = async () => {
    conversionLoading.value = true;
    conversionError.value = '';
    hideConversionTooltip();

    try {
        const response = await window.axios.get('/dashboard-api/sales/conversion', {
            params: {
                startDate: filters.value.startDate,
                endDate: filters.value.endDate,
                country: activeConversionTab.value,
            },
        });

        conversionData.value = response.data.data || conversionData.value;
    } catch (exception) {
        conversionError.value = exception.response?.data?.message || 'No fue posible cargar la conversion.';
    } finally {
        conversionLoading.value = false;
    }
};

const loadVisits = async () => {
    visitsLoading.value = true;
    visitsError.value = '';
    hideVisitsTooltip();

    try {
        const params = {
            startDate: filters.value.startDate,
            endDate: filters.value.endDate,
            country: activeVisitsTab.value,
        };

        if (activeVisitsTab.value !== 'general') {
            params.previousStartDate = visitsPreviousFilters.value.startDate;
            params.previousEndDate = visitsPreviousFilters.value.endDate;
        }

        const response = await window.axios.get('/dashboard-api/sales/visits', { params });

        visitsData.value = response.data.data || visitsData.value;
    } catch (exception) {
        visitsError.value = exception.response?.data?.message || 'No fue posible cargar las visitas.';
    } finally {
        visitsLoading.value = false;
    }
};

const loadSatisfaction = async () => {
    satisfactionLoading.value = true;
    satisfactionError.value = '';

    try {
        const response = await window.axios.get('/dashboard-api/sales/satisfaction');
        satisfactionData.value = {
            ...satisfactionData.value,
            ...(response.data.data || {}),
        };

        if (!activeSatisfactionStoreCountry.value && satisfactionStoreCountries.value.length) {
            activeSatisfactionStoreCountry.value = satisfactionStoreCountries.value[0];
        }
    } catch (exception) {
        satisfactionError.value = exception.response?.data?.message || 'No fue posible cargar satisfaccion.';
    } finally {
        satisfactionLoading.value = false;
    }
};

const loadCategories = async () => {
    categoriesLoading.value = true;
    categoriesError.value = '';

    try {
        const response = await window.axios.get('/dashboard-api/sales/categories', {
            params: {
                startDate: categoryFilters.value.startDate,
                endDate: categoryFilters.value.endDate,
            },
        });
        categoryData.value = {
            ...categoryData.value,
            ...(response.data.data || {}),
        };
    } catch (exception) {
        categoriesError.value = exception.response?.data?.message || 'No fue posible cargar categorias.';
    } finally {
        categoriesLoading.value = false;
    }
};

const loadSegments = async () => {
    segmentsLoading.value = true;
    segmentsError.value = '';

    try {
        const response = await window.axios.get('/dashboard-api/sales/segments');
        segmentsData.value = {
            ...segmentsData.value,
            ...(response.data.data || {}),
        };
    } catch (exception) {
        segmentsError.value = exception.response?.data?.message || 'No fue posible cargar segmentos.';
    } finally {
        segmentsLoading.value = false;
    }
};

const loadPaymentForms = async () => {
    paymentFormsLoading.value = true;
    paymentFormsError.value = '';

    try {
        const response = await window.axios.get('/dashboard-api/sales/payment-forms');
        paymentFormsData.value = {
            ...paymentFormsData.value,
            ...(response.data.data || {}),
        };
    } catch (exception) {
        paymentFormsError.value = exception.response?.data?.message || 'No fue posible cargar formas de pago.';
    } finally {
        paymentFormsLoading.value = false;
    }
};

const loadGeographic = async () => {
    geographicLoading.value = true;
    geographicError.value = '';

    try {
        const response = await window.axios.get('/dashboard-api/sales/geographic');
        geographicData.value = {
            ...geographicData.value,
            ...(response.data.data || {}),
        };
    } catch (exception) {
        geographicError.value = exception.response?.data?.message || 'No fue posible cargar geografico.';
    } finally {
        geographicLoading.value = false;
    }
};

const loadApp = async () => {
    appLoading.value = true;
    appError.value = '';

    try {
        const response = await window.axios.get('/dashboard-api/sales/app', {
            params: {
                year: appFilters.value.year,
            },
        });
        appData.value = {
            ...appData.value,
            ...(response.data.data || {}),
        };

        if (!appFilters.value.year && appData.value.filters?.year) {
            appFilters.value.year = appData.value.filters.year;
        }
    } catch (exception) {
        appError.value = exception.response?.data?.message || 'No fue posible cargar APP.';
    } finally {
        appLoading.value = false;
    }
};

const visitsPreviousFilters = ref({
    startDate: formatDate(new Date(sevenDaysAgo.getFullYear() - 1, sevenDaysAgo.getMonth(), sevenDaysAgo.getDate())),
    endDate: formatDate(new Date(today.getFullYear() - 1, today.getMonth(), today.getDate())),
});

const percent = (value) => `${Number(value || 0).toFixed(2)}%`;
const number2 = (value) => Number(value || 0).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});
const geographicBarWidth = (value) => `${Math.max(2, (Number(value || 0) / geographicMaxValue.value) * 100)}%`;
const appBarHeight = (value) => `${Math.max(4, (Number(value || 0) / appMaxValue.value) * 100)}%`;
const categoryBarWidth = (sale) => `${Math.max(2, (Number(sale || 0) / categoryMaxSale.value) * 100)}%`;
const exportCategoryCsv = () => {
    const rows = categoryRows.value;

    if (!rows.length) {
        return;
    }

    const header = ['Pais', 'Fecha', 'Categoria', 'Venta'];
    const body = rows.map((row) => [row.country, row.date, row.category, Number(row.sale || 0).toFixed(2)]);
    const csv = [header, ...body]
        .map((line) => line.map((value) => `"${String(value).replaceAll('"', '""')}"`).join(','))
        .join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `venta-categorias-${categoryFilters.value.startDate}-${categoryFilters.value.endDate}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
};
const otifClass = (value) => {
    const percentValue = Number(value || 0);

    if (percentValue < 25) {
        return 'stj-otif-red';
    }

    if (percentValue < 50) {
        return 'stj-otif-orange';
    }

    if (percentValue < 75) {
        return 'stj-otif-yellow';
    }

    return 'stj-otif-green';
};

watch(activeTab, (tab) => {
    if (tab !== 'conversion') {
        hideConversionTooltip();
        conversionExportMenuOpen.value = false;
    }

    if (tab !== 'visits') {
        hideVisitsTooltip();
        visitsExportMenuOpen.value = false;
    }

    if (tab === 'conversion' && conversionData.value.categories.length === 0) {
        loadConversion();
    }

    if (tab === 'visits' && visitsData.value.categories.length === 0) {
        loadVisits();
    }

    if (tab === 'satisfaction' && satisfactionData.value.byCountry.length === 0) {
        loadSatisfaction();
    }

    if (tab === 'categories' && categoryData.value.rows.length === 0) {
        loadCategories();
    }

    if (tab === 'segments' && segmentSalesRows.value.length === 0) {
        loadSegments();
    }

    if (tab === 'paymentForms' && paymentFormsData.value.store.length === 0) {
        loadPaymentForms();
    }

    if (tab === 'geographic' && geographicRows.value.length === 0) {
        loadGeographic();
    }

    if (tab === 'app' && appRows.value.length === 0) {
        loadApp();
    }
});

watch(activeConversionTab, () => {
    conversionExportMenuOpen.value = false;
    hideConversionTooltip();

    if (activeTab.value === 'conversion') {
        loadConversion();
    }
});

watch(activeVisitsTab, () => {
    visitsExportMenuOpen.value = false;
    hideVisitsTooltip();

    if (activeTab.value === 'visits') {
        loadVisits();
    }
});

watch(countries, ensureValidSalesCountry);
watch(() => filters.value.country, () => {
    hideSalesTooltip();
    hiddenSalesSeries.value = new Set();
    salesExportMenuOpen.value = false;
});

onMounted(() => {
    if (isStoreManagerDashboard.value) {
        loadStoreSummary();
        return;
    }

    if (!canSeeAnalyticsDashboard.value) {
        return;
    }

    ensureValidSalesCountry();
    loadChart();
});
</script>

<template>
    <Head title="Dashboard" />

    <AdminLayout>
        <section class="mx-auto w-full max-w-7xl">
            <div v-if="isStoreManagerDashboard" class="grid gap-5">
                <div class="app-surface rounded-lg border p-6" style="border-color: var(--stj-border);">
                    <p class="app-primary-text text-xs font-semibold uppercase">Tienda</p>
                    <h1 class="app-text mt-2 text-2xl font-bold">Resumen de pedidos</h1>
                    <p class="app-muted mt-1 text-sm">{{ user.storeLabel || storeCode }} | Mes actual</p>
                </div>

                <div v-if="storeSummaryError" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ storeSummaryError }}
                </div>

                <div class="relative grid gap-4 md:grid-cols-3">
                    <div v-if="storeSummaryLoading" class="app-muted absolute inset-0 z-10 flex items-center justify-center rounded-lg bg-white/70 text-sm">
                        Cargando indicadores...
                    </div>

                    <div
                        v-for="card in storeSummaryCards"
                        :key="card.label"
                        class="app-surface rounded-lg border p-5"
                        style="border-color: var(--stj-border);"
                    >
                        <p class="app-muted text-sm">{{ card.label }}</p>
                        <p class="app-text mt-3 text-3xl font-bold">{{ card.value }}</p>
                        <p class="app-text-soft mt-2 text-sm">{{ card.detail }}</p>
                    </div>
                </div>
            </div>

            <div v-else-if="!canSeeAnalyticsDashboard" class="app-surface rounded-lg border p-8" style="border-color: var(--stj-border);">
                <p class="app-primary-text text-xs font-semibold uppercase">St. Jack's ecommerce</p>
                <h1 class="app-text mt-3 text-2xl font-bold">Bienvenido/a al nuevo dashboard de St. Jack's para ecommerce</h1>
                <p class="app-muted mt-3 max-w-2xl text-sm">
                    Hola {{ welcomeName }}. Usa el menu lateral para acceder a los modulos disponibles para tu perfil.
                </p>
            </div>

            <div v-else class="app-surface rounded-lg border">
                <div class="app-border-soft overflow-x-auto border-b">
                    <nav class="flex min-w-max px-4" aria-label="Dashboard tabs">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            type="button"
                            :class="[
                                'border-b-2 px-4 py-3 text-sm font-medium transition',
                                activeTab === tab.id
                                    ? 'border-blue-500 app-text'
                                    : 'border-transparent app-primary-text hover:bg-blue-50/10',
                            ]"
                            @click="activeTab = tab.id"
                        >
                            {{ tab.label }}
                        </button>
                    </nav>
                </div>

                <div v-if="activeTab === 'sales'" class="app-border-soft border-b p-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="app-muted text-sm">Dashboard Analytics</p>
                            <h2 class="app-text mt-1 text-xl font-semibold">Ventas por pais</h2>
                        </div>

                        <form class="grid gap-3 sm:grid-cols-4" @submit.prevent="loadChart">
                            <label class="block">
                                <span class="app-muted text-sm">Inicio</span>
                                <input v-model="filters.startDate" type="date" class="stj-dashboard-input mt-1">
                            </label>
                            <label class="block">
                                <span class="app-muted text-sm">Fin</span>
                                <input v-model="filters.endDate" type="date" class="stj-dashboard-input mt-1">
                            </label>
                            <label class="block">
                                <span class="app-muted text-sm">Pais</span>
                                <select v-model="filters.country" class="stj-dashboard-input mt-1">
                                    <option v-for="country in countries" :key="country.id" :value="country.id">
                                        {{ country.label }}
                                    </option>
                                </select>
                            </label>
                            <button type="submit" class="mt-6 h-10 rounded-md app-primary px-4 text-sm font-semibold transition hover:opacity-90">
                                Actualizar
                            </button>
                        </form>
                    </div>
                </div>

                <div v-if="activeTab === 'sales'" class="p-5">
                    <div v-if="error" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ error }}
                    </div>

                    <div class="relative min-h-[360px] overflow-x-auto pt-10">
                        <div v-if="loading" class="app-muted absolute inset-0 z-10 flex items-center justify-center bg-white/70 text-sm">
                            Cargando ventas...
                        </div>

                        <div class="absolute right-3 top-0 z-20">
                            <button
                                type="button"
                                class="stj-chart-menu-button"
                                aria-label="Descargar grafico de ventas"
                                :aria-expanded="salesExportMenuOpen"
                                @click="salesExportMenuOpen = !salesExportMenuOpen"
                            >
                                <span></span>
                                <span></span>
                                <span></span>
                            </button>
                            <div v-if="salesExportMenuOpen" class="stj-chart-menu">
                                <button type="button" @click="exportSalesPng">Descargar PNG</button>
                                <button type="button" @click="exportSalesCsv">Descargar CSV</button>
                                <button type="button" @click="exportSalesSvg">Descargar SVG</button>
                            </div>
                        </div>

                        <svg
                            ref="salesChartSvg"
                            class="min-w-[760px]"
                            :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
                            role="img"
                            aria-label="Grafico de ventas por pais"
                        >
                            <g v-for="tick in yTicks" :key="tick">
                                <line
                                    :x1="padding.left"
                                    :x2="chartWidth - padding.right"
                                    :y1="yFor(tick)"
                                    :y2="yFor(tick)"
                                    stroke="var(--stj-border-soft)"
                                />
                                <text
                                    :x="padding.left - 10"
                                    :y="yFor(tick) + 4"
                                    text-anchor="end"
                                    class="fill-current app-muted"
                                    font-size="12"
                                >
                                    {{ tick }}
                                </text>
                            </g>

                            <g v-for="(date, index) in chartData.categories" :key="date">
                                <text
                                    :x="xFor(index)"
                                    :y="chartHeight - 18"
                                    text-anchor="middle"
                                    class="fill-current app-text-soft"
                                    font-size="12"
                                >
                                    {{ date.slice(5) }}
                                </text>
                            </g>

                            <g v-for="serie in visibleSeries" :key="serie.key">
                                <path
                                    :d="linePath(serie.data)"
                                    fill="none"
                                    :stroke="palette[serie.key]"
                                    :stroke-width="serie.period === 'current' ? 3 : 3"
                                    :stroke-dasharray="serie.period === 'previous' ? '6 7' : '0'"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                                <circle
                                    v-for="(value, index) in serie.data"
                                    :key="`${serie.key}-${index}`"
                                    :cx="xFor(index)"
                                    :cy="yFor(value)"
                                    :r="salesTooltip?.key === `${serie.key}-${index}` ? 5 : 3"
                                    :fill="palette[serie.key]"
                                    class="cursor-pointer transition"
                                    tabindex="0"
                                    @mouseenter="showSalesTooltip(serie, index, value)"
                                    @focus="showSalesTooltip(serie, index, value)"
                                    @mouseleave="hideSalesTooltip"
                                    @blur="hideSalesTooltip"
                                />
                            </g>

                            <g v-if="salesTooltip" class="pointer-events-none">
                                <line
                                    :x1="salesTooltip.x"
                                    :x2="salesTooltip.x"
                                    :y1="padding.top"
                                    :y2="chartHeight - padding.bottom"
                                    stroke="var(--stj-border)"
                                    stroke-dasharray="4 4"
                                />
                                <rect
                                    :x="salesTooltipX"
                                    :y="salesTooltipY"
                                    :width="salesTooltipWidth"
                                    :height="salesTooltipHeight"
                                    rx="6"
                                    fill="var(--stj-surface)"
                                    stroke="var(--stj-border)"
                                />
                                <text
                                    :x="salesTooltipX + 12"
                                    :y="salesTooltipY + 20"
                                    class="fill-current app-text"
                                    font-size="12"
                                    font-weight="700"
                                >
                                    {{ salesTooltip.date }}
                                </text>
                                <circle :cx="salesTooltipX + 14" :cy="salesTooltipY + 41" r="5" :fill="salesTooltip.color" />
                                <text
                                    :x="salesTooltipX + 26"
                                    :y="salesTooltipY + 45"
                                    class="fill-current app-text"
                                    font-size="12"
                                >
                                    {{ salesTooltip.country }} ({{ salesTooltip.year }})
                                </text>
                                <text
                                    :x="salesTooltipX + 12"
                                    :y="salesTooltipY + 67"
                                    class="fill-current app-muted"
                                    font-size="11"
                                >
                                    Pais: {{ salesTooltip.country }} | Año: {{ salesTooltip.year }}
                                </text>
                                <text
                                    :x="salesTooltipX + 12"
                                    :y="salesTooltipY + 87"
                                    class="fill-current app-text"
                                    font-size="12"
                                    font-weight="700"
                                >
                                    Monto: {{ amount(salesTooltip.value) }}
                                </text>
                            </g>
                        </svg>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-3">
                        <button
                            v-for="serie in salesSeries"
                            :key="serie.key"
                            type="button"
                            :class="[
                                'stj-chart-legend-button app-surface-soft app-border flex items-center gap-2 rounded-md border px-3 py-2 text-sm',
                                salesSeriesIsHidden(serie.key) ? 'stj-chart-legend-button-hidden' : '',
                            ]"
                            :aria-pressed="!salesSeriesIsHidden(serie.key)"
                            @click="toggleSalesSeries(serie.key)"
                        >
                            <span class="h-3 w-3 rounded-full" :style="{ background: palette[serie.key] }"></span>
                            <span class="app-text-soft">{{ serie.label }}</span>
                        </button>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div
                            v-for="row in totals"
                            :key="row.key"
                            class="app-surface-soft app-border rounded-md border p-3"
                        >
                            <p class="app-muted text-xs">{{ row.label }}</p>
                            <p class="app-text mt-1 text-lg font-semibold">{{ money(row.total) }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="activeTab === 'conversion'" class="p-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="app-muted text-sm">Dashboard Analytics</p>
                            <h2 class="app-text mt-1 text-xl font-semibold">% Conversion</h2>
                        </div>

                        <form class="grid gap-3 sm:grid-cols-3" @submit.prevent="loadConversion">
                            <label class="block">
                                <span class="app-muted text-sm">Inicio</span>
                                <input v-model="filters.startDate" type="date" class="stj-dashboard-input mt-1">
                            </label>
                            <label class="block">
                                <span class="app-muted text-sm">Fin</span>
                                <input v-model="filters.endDate" type="date" class="stj-dashboard-input mt-1">
                            </label>
                            <button type="submit" class="mt-6 h-10 rounded-md app-primary px-4 text-sm font-semibold transition hover:opacity-90">
                                Generar
                            </button>
                        </form>
                    </div>

                    <div class="app-border-soft mt-5 overflow-x-auto border-b">
                        <nav class="flex min-w-max" aria-label="Conversion tabs">
                            <button
                                v-for="tab in conversionTabs"
                                :key="tab.id"
                                type="button"
                                :class="[
                                    'border-b-2 px-4 py-3 text-sm font-medium transition',
                                    activeConversionTab === tab.id
                                        ? 'border-blue-500 app-text'
                                        : 'border-transparent app-primary-text hover:bg-blue-50/10',
                                ]"
                                @click="activeConversionTab = tab.id"
                            >
                                {{ tab.label }}
                            </button>
                        </nav>
                    </div>

                    <div v-if="conversionError" class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ conversionError }}
                    </div>

                    <div class="relative mt-5 min-h-[360px] overflow-x-auto pt-10">
                        <div v-if="conversionLoading" class="app-muted absolute inset-0 z-10 flex items-center justify-center bg-white/70 text-sm">
                            Cargando conversion...
                        </div>

                        <div class="absolute right-3 top-0 z-20">
                            <button
                                type="button"
                                class="stj-chart-menu-button"
                                aria-label="Descargar grafico de conversion"
                                :aria-expanded="conversionExportMenuOpen"
                                @click="conversionExportMenuOpen = !conversionExportMenuOpen"
                            >
                                <span></span>
                                <span></span>
                                <span></span>
                            </button>
                            <div v-if="conversionExportMenuOpen" class="stj-chart-menu">
                                <button type="button" @click="exportConversionPng">Descargar PNG</button>
                                <button type="button" @click="exportConversionCsv">Descargar CSV</button>
                                <button type="button" @click="exportConversionSvg">Descargar SVG</button>
                            </div>
                        </div>

                        <svg
                            ref="conversionChartSvg"
                            class="min-w-[760px]"
                            :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
                            role="img"
                            aria-label="Grafico de conversion"
                        >
                            <g v-for="tick in conversionYTicks" :key="tick">
                                <line
                                    :x1="padding.left"
                                    :x2="chartWidth - padding.right"
                                    :y1="conversionYFor(tick)"
                                    :y2="conversionYFor(tick)"
                                    stroke="var(--stj-border-soft)"
                                />
                                <text
                                    :x="padding.left - 10"
                                    :y="conversionYFor(tick) + 4"
                                    text-anchor="end"
                                    class="fill-current app-muted"
                                    font-size="12"
                                >
                                    {{ percent(tick) }}
                                </text>
                            </g>

                            <g v-for="(date, index) in conversionData.categories" :key="date">
                                <text
                                    :x="conversionXFor(index)"
                                    :y="chartHeight - 18"
                                    text-anchor="middle"
                                    class="fill-current app-text-soft"
                                    font-size="12"
                                >
                                    {{ date.slice(5) }}
                                </text>
                            </g>

                            <g v-for="serie in conversionData.series" :key="serie.key">
                                <path
                                    :d="conversionLinePath(serie.data)"
                                    fill="none"
                                    :stroke="palette[serie.key]"
                                    stroke-width="3"
                                    :stroke-dasharray="serie.period === 'previous' ? '6 7' : '0'"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                                <circle
                                    v-for="(value, index) in serie.data"
                                    :key="`${serie.key}-${index}`"
                                    :cx="conversionXFor(index)"
                                    :cy="conversionYFor(value)"
                                    :r="conversionTooltip?.key === `conversion-${index}` ? 5 : 3"
                                    :fill="palette[serie.key]"
                                    class="cursor-pointer transition"
                                    tabindex="0"
                                    @mouseenter="showConversionTooltip(index, value)"
                                    @focus="showConversionTooltip(index, value)"
                                    @mouseleave="hideConversionTooltip"
                                    @blur="hideConversionTooltip"
                                />
                            </g>

                            <g v-if="conversionTooltip" class="pointer-events-none">
                                <line
                                    :x1="conversionTooltip.x"
                                    :x2="conversionTooltip.x"
                                    :y1="padding.top"
                                    :y2="chartHeight - padding.bottom"
                                    stroke="var(--stj-border)"
                                    stroke-dasharray="4 4"
                                />
                                <rect
                                    :x="conversionTooltipX"
                                    :y="conversionTooltipY"
                                    :width="conversionTooltipWidth"
                                    :height="conversionTooltipHeight"
                                    rx="6"
                                    fill="var(--stj-surface)"
                                    stroke="var(--stj-border)"
                                />
                                <text
                                    :x="conversionTooltipX + 12"
                                    :y="conversionTooltipY + 22"
                                    class="fill-current app-text"
                                    font-size="13"
                                    font-weight="700"
                                >
                                    {{ conversionTooltip.date }}
                                </text>
                                <g v-for="(row, rowIndex) in conversionTooltip.rows" :key="row.key">
                                    <circle
                                        :cx="conversionTooltipX + 15"
                                        :cy="conversionTooltipY + 45 + rowIndex * 22"
                                        r="5"
                                        :fill="row.color"
                                    />
                                    <text
                                        :x="conversionTooltipX + 28"
                                        :y="conversionTooltipY + 49 + rowIndex * 22"
                                        class="fill-current app-text"
                                        font-size="12"
                                    >
                                        {{ row.label }}: {{ percent(row.value) }}
                                    </text>
                                </g>
                            </g>
                        </svg>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="app-surface-soft app-border rounded-md border p-3">
                            <p class="app-muted text-xs">Visitas</p>
                            <p class="app-text mt-1 text-lg font-semibold">{{ conversionTotals.visits || 0 }}</p>
                        </div>
                        <div class="app-surface-soft app-border rounded-md border p-3">
                            <p class="app-muted text-xs">Compras</p>
                            <p class="app-text mt-1 text-lg font-semibold">{{ conversionTotals.orders || 0 }}</p>
                        </div>
                        <div class="app-surface-soft app-border rounded-md border p-3">
                            <p class="app-muted text-xs">Conversion actual</p>
                            <p class="app-text mt-1 text-lg font-semibold">{{ percent(conversionTotals.rate) }}</p>
                        </div>
                        <div class="app-surface-soft app-border rounded-md border p-3">
                            <p class="app-muted text-xs">Conversion anterior</p>
                            <p class="app-text mt-1 text-lg font-semibold">{{ percent(conversionTotals.previousRate) }}</p>
                        </div>
                    </div>

                    <div class="mt-5 overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="app-surface-soft app-text-soft">
                                <tr>
                                    <th class="px-3 py-2 font-semibold">Fecha</th>
                                    <th class="px-3 py-2 text-right font-semibold">Visitas</th>
                                    <th class="px-3 py-2 text-right font-semibold">Compras</th>
                                    <th class="px-3 py-2 text-right font-semibold">%</th>
                                    <th class="px-3 py-2 text-right font-semibold">Fecha anterior</th>
                                    <th class="px-3 py-2 text-right font-semibold">% anterior</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!conversionData.rows.length">
                                    <td colspan="6" class="app-muted px-3 py-6 text-center">Sin datos para el rango seleccionado.</td>
                                </tr>
                                <tr v-for="row in conversionData.rows" :key="row.date" class="app-border-soft border-b">
                                    <td class="px-3 py-2">{{ row.date }}</td>
                                    <td class="px-3 py-2 text-right">{{ row.visits }}</td>
                                    <td class="px-3 py-2 text-right">{{ row.orders }}</td>
                                    <td class="px-3 py-2 text-right">{{ percent(row.rate) }}</td>
                                    <td class="px-3 py-2 text-right">{{ row.previousDate }}</td>
                                    <td class="px-3 py-2 text-right">{{ percent(row.previousRate) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-else-if="activeTab === 'satisfaction'" class="p-5">
                    <div class="text-center">
                        <p class="app-muted text-sm">Dashboard Analytics</p>
                        <h2 class="app-text mt-1 text-2xl font-semibold">
                            Indicadores de servicio al cliente - {{ satisfactionData.filters.monthLabel || '' }}
                        </h2>
                    </div>

                    <div class="mt-6 flex flex-wrap justify-center gap-5">
                        <div
                            v-for="item in satisfactionData.legend"
                            :key="item.label"
                            class="flex items-center gap-2 text-sm"
                        >
                            <span class="h-3.5 w-3.5 rounded-full" :style="{ background: item.color }"></span>
                            <span class="app-text-soft">{{ item.label }}</span>
                        </div>
                    </div>

                    <div v-if="satisfactionError" class="mt-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ satisfactionError }}
                    </div>

                    <div class="relative mt-6">
                        <div v-if="satisfactionLoading" class="app-muted absolute inset-0 z-10 flex items-center justify-center bg-white/70 text-sm">
                            Cargando satisfaccion...
                        </div>

                        <div class="space-y-5">
                            <div>
                                <h3 class="app-text text-sm font-bold uppercase">OTIF</h3>
                                <div class="app-border-soft mt-2 border-t pt-2 text-sm leading-6">
                                    El indicador "OTIF" significa "on-time" (pedidos a tiempo) e "in-full" (pedidos completos). El OTIF exige que se cumplan ambas cosas al mismo tiempo. El OTIF mide desde la perspectiva del cliente y captura dos requerimientos valorados por este, muy sencillos pero claves: que le entreguen lo que pidio en el tiempo en que lo pidio.
                                </div>
                                <p class="app-text-soft mt-4 text-sm">
                                    Tiempo estimado de entrega de pedidos: {{ satisfactionData.filters.deliveryDays || 7 }} dias
                                </p>
                            </div>

                            <div class="grid gap-6 lg:grid-cols-2">
                                <section>
                                    <h3 class="stj-satisfaction-title">Por pais</h3>
                                    <div class="overflow-x-auto">
                                        <table class="stj-satisfaction-table">
                                            <thead>
                                                <tr>
                                                    <th>Pais</th>
                                                    <th>Origen</th>
                                                    <th class="text-right">OTIF</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-if="!satisfactionData.byCountry.length">
                                                    <td colspan="3" class="app-muted text-center">Sin datos.</td>
                                                </tr>
                                                <tr v-for="row in satisfactionData.byCountry" :key="`${row.country}-${row.origin}`">
                                                    <td>{{ row.country }}</td>
                                                    <td>{{ row.origin }}</td>
                                                    <td class="text-right">
                                                        <span :class="['stj-otif', otifClass(row.otif)]">{{ percent(row.otif) }}</span>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </section>

                                <section>
                                    <h3 class="stj-satisfaction-title">Por tipo de pago</h3>
                                    <div class="overflow-x-auto">
                                        <table class="stj-satisfaction-table">
                                            <thead>
                                                <tr>
                                                    <th>Pais</th>
                                                    <th>Origen</th>
                                                    <th>Tipo de pago</th>
                                                    <th class="text-right">OTIF</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-if="!satisfactionData.byPaymentType.length">
                                                    <td colspan="4" class="app-muted text-center">Sin datos.</td>
                                                </tr>
                                                <tr v-for="row in satisfactionData.byPaymentType" :key="`${row.country}-${row.origin}-${row.paymentType}`">
                                                    <td>{{ row.country }}</td>
                                                    <td>{{ row.origin }}</td>
                                                    <td>{{ row.paymentType }}</td>
                                                    <td class="text-right">
                                                        <span :class="['stj-otif', otifClass(row.otif)]">{{ percent(row.otif) }}</span>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </section>

                                <section>
                                    <h3 class="stj-satisfaction-title">Por tipo de checkout</h3>
                                    <div class="overflow-x-auto">
                                        <table class="stj-satisfaction-table">
                                            <thead>
                                                <tr>
                                                    <th>Pais</th>
                                                    <th>Origen</th>
                                                    <th>Checkout</th>
                                                    <th class="text-right">OTIF</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-if="!satisfactionData.byCheckout.length">
                                                    <td colspan="4" class="app-muted text-center">Sin datos.</td>
                                                </tr>
                                                <tr v-for="row in satisfactionData.byCheckout" :key="`${row.country}-${row.origin}-${row.checkout}`">
                                                    <td>{{ row.country }}</td>
                                                    <td>{{ row.origin }}</td>
                                                    <td>{{ row.checkout }}</td>
                                                    <td class="text-right">
                                                        <span :class="['stj-otif', otifClass(row.otif)]">{{ percent(row.otif) }}</span>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </section>

                                <section>
                                    <h3 class="app-text text-sm font-semibold uppercase">Por tienda</h3>
                                    <div class="app-border-soft mt-2 overflow-x-auto border-b">
                                        <nav class="flex min-w-max" aria-label="OTIF por tienda">
                                            <button
                                                v-for="country in satisfactionStoreCountries"
                                                :key="country"
                                                type="button"
                                                :class="[
                                                    'border-b-2 px-4 py-2 text-sm font-medium transition',
                                                    activeSatisfactionStoreCountry === country
                                                        ? 'border-blue-500 app-text'
                                                        : 'border-transparent app-primary-text hover:bg-blue-50/10',
                                                ]"
                                                @click="activeSatisfactionStoreCountry = country"
                                            >
                                                {{ country }}
                                            </button>
                                        </nav>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="stj-satisfaction-table">
                                            <thead>
                                                <tr>
                                                    <th>Pais</th>
                                                    <th>Tienda</th>
                                                    <th class="text-right">OTIF</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-if="!satisfactionStoreRows.length">
                                                    <td colspan="3" class="app-muted text-center">Sin datos.</td>
                                                </tr>
                                                <tr v-for="row in satisfactionStoreRows" :key="`${row.country}-${row.store}`">
                                                    <td>{{ row.country }}</td>
                                                    <td>{{ row.store }}</td>
                                                    <td class="text-right">
                                                        <span :class="['stj-otif', otifClass(row.otif)]">{{ percent(row.otif) }}</span>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </section>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else-if="activeTab === 'categories'" class="p-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="app-muted text-sm">Dashboard Analytics</p>
                            <h2 class="app-text mt-1 text-xl font-semibold">Categorias</h2>
                            <p class="app-text-soft mt-2 text-sm">Venta en dolares</p>
                        </div>

                        <form class="grid gap-3 lg:grid-cols-[160px_160px_180px_auto_auto]" @submit.prevent="loadCategories">
                            <label class="block">
                                <span class="app-muted text-sm">Fecha inicial</span>
                                <input v-model="categoryFilters.startDate" type="date" class="stj-dashboard-input mt-1">
                            </label>
                            <label class="block">
                                <span class="app-muted text-sm">Fecha final</span>
                                <input v-model="categoryFilters.endDate" type="date" class="stj-dashboard-input mt-1">
                            </label>
                            <label class="block">
                                <span class="app-muted text-sm">Pais</span>
                                <select v-model="activeCategoryCountry" class="stj-dashboard-input mt-1">
                                    <option v-for="country in categoryData.countries" :key="country.id" :value="String(country.id)">
                                        {{ country.label }}
                                    </option>
                                </select>
                            </label>
                            <button type="submit" class="mt-6 h-10 rounded-md app-primary px-4 text-sm font-semibold transition hover:opacity-90">
                                {{ categoriesLoading ? 'Generando...' : 'Generar' }}
                            </button>
                            <button
                                type="button"
                                class="mt-6 h-10 rounded-md border px-4 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-60"
                                style="border-color: var(--stj-border); color: var(--stj-text);"
                                :disabled="!categoryRows.length"
                                @click="exportCategoryCsv"
                            >
                                Exportar
                            </button>
                        </form>
                    </div>

                    <div v-if="categoriesError" class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ categoriesError }}
                    </div>

                    <div class="relative mt-6 min-h-[360px]">
                        <div v-if="categoriesLoading" class="app-muted absolute inset-0 z-10 flex items-center justify-center bg-white/70 text-sm">
                            Cargando categorias...
                        </div>

                        <div v-if="!categoryTotals.length" class="app-muted rounded-md border p-6 text-center text-sm" style="border-color: var(--stj-border);">
                            Sin datos para los filtros seleccionados.
                        </div>

                        <div v-else class="space-y-3">
                            <div
                                v-for="row in categoryTotals"
                                :key="row.category"
                                class="grid gap-3 lg:grid-cols-[220px_1fr_90px] lg:items-center"
                            >
                                <div class="app-text-soft truncate text-sm font-medium" :title="row.category">
                                    {{ row.category }}
                                </div>
                                <div class="h-8 rounded-md bg-slate-100">
                                    <div
                                        class="flex h-8 items-center justify-end rounded-md bg-blue-600 px-2 text-xs font-semibold text-white"
                                        :style="{ width: categoryBarWidth(row.sale) }"
                                    >
                                        {{ row.sale.toFixed(2) }}
                                    </div>
                                </div>
                                <div class="app-text text-right text-sm font-semibold">
                                    {{ row.sale.toFixed(2) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="app-surface-soft app-text-soft">
                                <tr>
                                    <th class="px-3 py-2 font-semibold">Pais</th>
                                    <th class="px-3 py-2 font-semibold">Fecha</th>
                                    <th class="px-3 py-2 font-semibold">Categoria</th>
                                    <th class="px-3 py-2 text-right font-semibold">Venta</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!categoryRows.length">
                                    <td colspan="4" class="app-muted px-3 py-6 text-center">Sin datos para el rango seleccionado.</td>
                                </tr>
                                <tr v-for="row in categoryRows" :key="`${row.country}-${row.date}-${row.category}`" class="app-border-soft border-b">
                                    <td class="px-3 py-2">{{ row.country }}</td>
                                    <td class="px-3 py-2">{{ row.date }}</td>
                                    <td class="px-3 py-2">{{ row.category }}</td>
                                    <td class="px-3 py-2 text-right">{{ Number(row.sale || 0).toFixed(2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-else-if="activeTab === 'segments'" class="p-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p class="app-muted text-sm">Dashboard Analytics</p>
                            <h2 class="app-text mt-1 text-xl font-semibold">Segmentos</h2>
                            <p class="app-text-soft mt-2 text-sm">{{ segmentsData.filters.monthLabel || '' }}</p>
                        </div>

                        <button type="button" class="h-10 rounded-md app-primary px-4 text-sm font-semibold transition hover:opacity-90" @click="loadSegments">
                            {{ segmentsLoading ? 'Actualizando...' : 'Actualizar' }}
                        </button>
                    </div>

                    <div v-if="segmentsError" class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ segmentsError }}
                    </div>

                    <div class="relative mt-6">
                        <div v-if="segmentsLoading" class="app-muted absolute inset-0 z-10 flex items-center justify-center bg-white/70 text-sm">
                            Cargando segmentos...
                        </div>

                        <div class="grid gap-6 xl:grid-cols-2">
                            <section class="overflow-hidden rounded-lg border" style="border-color: var(--stj-border);">
                                <div class="app-surface-soft app-border-soft border-b px-4 py-3">
                                    <h3 class="app-text text-sm font-semibold">Venta por canal, tipo de pago y checkout</h3>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-left text-sm">
                                        <thead class="app-surface-soft app-text-soft">
                                            <tr>
                                                <th class="px-3 py-2 font-semibold"></th>
                                                <th class="px-3 py-2 font-semibold">Tipo pago</th>
                                                <th class="px-3 py-2 text-right font-semibold">Tienda</th>
                                                <th class="px-3 py-2 text-right font-semibold">Domicilio</th>
                                                <th class="px-3 py-2 text-right font-semibold">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-if="!segmentSalesRows.length">
                                                <td colspan="5" class="app-muted px-3 py-6 text-center">Sin datos del mes actual.</td>
                                            </tr>
                                            <tr v-for="row in segmentSalesRows" :key="`${row.key}-${row.paymentType}`" class="app-border-soft border-b">
                                                <td class="px-3 py-2">{{ row.label }}</td>
                                                <td class="px-3 py-2">{{ row.paymentType }}</td>
                                                <td class="px-3 py-2 text-right">{{ number2(row.store) }}</td>
                                                <td class="px-3 py-2 text-right">{{ number2(row.delivery) }}</td>
                                                <td class="px-3 py-2 text-right font-semibold">{{ number2(row.total) }}</td>
                                            </tr>
                                            <tr v-for="row in segmentTotals.byPaymentType" :key="`total-${row.paymentType}`" class="app-surface-soft font-semibold">
                                                <td class="px-3 py-2">{{ row.paymentType }}</td>
                                                <td class="px-3 py-2">TOTAL</td>
                                                <td class="px-3 py-2 text-right">{{ number2(row.store) }}</td>
                                                <td class="px-3 py-2 text-right">{{ number2(row.delivery) }}</td>
                                                <td class="px-3 py-2 text-right">{{ number2(row.total) }}</td>
                                            </tr>
                                            <tr class="app-primary text-sm font-bold text-white">
                                                <td class="px-3 py-2">TOTAL</td>
                                                <td class="px-3 py-2"></td>
                                                <td class="px-3 py-2 text-right">{{ number2(segmentTotals.store) }}</td>
                                                <td class="px-3 py-2 text-right">{{ number2(segmentTotals.delivery) }}</td>
                                                <td class="px-3 py-2 text-right">{{ number2(segmentTotals.total) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <section class="overflow-hidden rounded-lg border" style="border-color: var(--stj-border);">
                                <div class="app-surface-soft app-border-soft border-b px-4 py-3">
                                    <h3 class="app-text text-sm font-semibold">Ticket promedio por canal, tipo de pago y checkout</h3>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-left text-sm">
                                        <thead class="app-surface-soft app-text-soft">
                                            <tr>
                                                <th class="px-3 py-2 font-semibold"></th>
                                                <th class="px-3 py-2 font-semibold">Tipo pago</th>
                                                <th class="px-3 py-2 text-right font-semibold">Tienda</th>
                                                <th class="px-3 py-2 text-right font-semibold">Domicilio</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-if="!segmentTicketRows.length">
                                                <td colspan="4" class="app-muted px-3 py-6 text-center">Sin datos del mes actual.</td>
                                            </tr>
                                            <tr
                                                v-for="row in segmentTicketRows"
                                                :key="`ticket-${row.key}-${row.paymentType}`"
                                                class="app-border-soft border-b"
                                                :class="row.paymentType === 'EFECTIVO' ? 'stj-segment-cash' : 'stj-segment-card'"
                                            >
                                                <td class="px-3 py-2">{{ row.label }}</td>
                                                <td class="px-3 py-2">{{ row.paymentType }}</td>
                                                <td class="px-3 py-2 text-right">{{ number2(row.store) }}</td>
                                                <td class="px-3 py-2 text-right">{{ number2(row.delivery) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="app-border-soft flex gap-5 border-t px-4 py-3 text-sm">
                                    <span><span class="stj-segment-legend stj-segment-cash"></span> Efectivo</span>
                                    <span><span class="stj-segment-legend stj-segment-card"></span> Tarjeta</span>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>

                <div v-else-if="activeTab === 'paymentForms'" class="p-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p class="app-muted text-sm">Dashboard Analytics</p>
                            <h2 class="app-text mt-1 text-xl font-semibold">Formas de pago</h2>
                            <p class="app-text-soft mt-2 text-sm">{{ paymentFormsData.filters.monthLabel || '' }}</p>
                        </div>

                        <button type="button" class="h-10 rounded-md app-primary px-4 text-sm font-semibold transition hover:opacity-90" @click="loadPaymentForms">
                            {{ paymentFormsLoading ? 'Actualizando...' : 'Actualizar' }}
                        </button>
                    </div>

                    <div v-if="paymentFormsError" class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ paymentFormsError }}
                    </div>

                    <div class="relative mt-6 space-y-6">
                        <div v-if="paymentFormsLoading" class="app-muted absolute inset-0 z-10 flex items-center justify-center bg-white/70 text-sm">
                            Cargando formas de pago...
                        </div>

                        <section class="overflow-hidden rounded-lg border" style="border-color: var(--stj-border);">
                            <div class="app-surface-soft app-border-soft border-b px-4 py-3">
                                <h3 class="app-text text-sm font-semibold">Venta por canal checkout tienda</h3>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-left text-sm">
                                    <thead class="app-surface-soft app-text-soft">
                                        <tr>
                                            <th class="px-3 py-2 font-semibold"></th>
                                            <th
                                                v-for="segment in paymentFormSegments"
                                                :key="`store-head-${segment.key}`"
                                                class="px-3 py-2 text-right font-semibold"
                                            >
                                                {{ segment.label }}
                                            </th>
                                            <th class="px-3 py-2 text-right font-semibold">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!paymentFormsData.store.length">
                                            <td :colspan="paymentFormSegments.length + 2" class="app-muted px-3 py-6 text-center">Sin datos del mes actual.</td>
                                        </tr>
                                        <tr v-for="row in paymentFormsData.store" :key="`store-${row.issuer}`" class="app-border-soft border-b">
                                            <td class="px-3 py-2 font-semibold">{{ row.issuer }}</td>
                                            <td
                                                v-for="segment in paymentFormSegments"
                                                :key="`store-${row.issuer}-${segment.key}`"
                                                class="px-3 py-2 text-right"
                                            >
                                                {{ number2(row.values?.[segment.key]) }}
                                            </td>
                                            <td class="px-3 py-2 text-right font-semibold">{{ number2(row.total) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="overflow-hidden rounded-lg border" style="border-color: var(--stj-border);">
                            <div class="app-surface-soft app-border-soft border-b px-4 py-3">
                                <h3 class="app-text text-sm font-semibold">Venta por canal checkout domicilio</h3>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-left text-sm">
                                    <thead class="app-surface-soft app-text-soft">
                                        <tr>
                                            <th class="px-3 py-2 font-semibold"></th>
                                            <th
                                                v-for="segment in paymentFormSegments"
                                                :key="`delivery-head-${segment.key}`"
                                                class="px-3 py-2 text-right font-semibold"
                                            >
                                                {{ segment.label }}
                                            </th>
                                            <th class="px-3 py-2 text-right font-semibold">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!paymentFormsData.delivery.length">
                                            <td :colspan="paymentFormSegments.length + 2" class="app-muted px-3 py-6 text-center">Sin datos del mes actual.</td>
                                        </tr>
                                        <tr v-for="row in paymentFormsData.delivery" :key="`delivery-${row.issuer}`" class="app-border-soft border-b">
                                            <td class="px-3 py-2 font-semibold">{{ row.issuer }}</td>
                                            <td
                                                v-for="segment in paymentFormSegments"
                                                :key="`delivery-${row.issuer}-${segment.key}`"
                                                class="px-3 py-2 text-right"
                                            >
                                                {{ number2(row.values?.[segment.key]) }}
                                            </td>
                                            <td class="px-3 py-2 text-right font-semibold">{{ number2(row.total) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                </div>

                <div v-else-if="activeTab === 'geographic'" class="p-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p class="app-muted text-sm">Dashboard Analytics</p>
                            <h2 class="app-text mt-1 text-xl font-semibold">Geografico</h2>
                            <p class="app-text-soft mt-2 text-sm">
                                {{ geographicData.filters.country || 'El Salvador' }} · {{ geographicData.filters.monthLabel || '' }}
                            </p>
                        </div>

                        <button type="button" class="h-10 rounded-md app-primary px-4 text-sm font-semibold transition hover:opacity-90" @click="loadGeographic">
                            {{ geographicLoading ? 'Actualizando...' : 'Actualizar' }}
                        </button>
                    </div>

                    <div v-if="geographicError" class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ geographicError }}
                    </div>

                    <div class="relative mt-6 grid gap-6 lg:grid-cols-[1fr_380px]">
                        <div v-if="geographicLoading" class="app-muted absolute inset-0 z-10 flex items-center justify-center bg-white/70 text-sm">
                            Cargando geografico...
                        </div>

                        <section class="overflow-hidden rounded-lg border p-5" style="border-color: var(--stj-border);">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="app-text text-sm font-semibold">Venta por departamento</h3>
                                    <p class="app-muted mt-1 text-xs">Montos en dolares estadounidenses</p>
                                </div>
                                <div class="text-right">
                                    <p class="app-muted text-xs">Total</p>
                                    <p class="app-text text-xl font-bold">{{ number2(geographicData.summary.total) }}</p>
                                </div>
                            </div>

                            <div v-if="!geographicRows.length" class="app-muted mt-5 rounded-md border p-6 text-center text-sm" style="border-color: var(--stj-border);">
                                Sin ventas por departamento para el mes actual.
                            </div>

                            <div v-else class="mt-5 grid gap-3 md:grid-cols-2">
                                <div
                                    v-for="row in geographicRows"
                                    :key="`${row.id}-${row.department}`"
                                    class="rounded-md border p-3"
                                    style="border-color: var(--stj-border);"
                                >
                                    <div class="mb-2 flex items-center justify-between gap-3">
                                        <span class="app-text truncate text-sm font-semibold" :title="row.department">{{ row.department }}</span>
                                        <span class="app-muted text-xs">{{ row.id || 'N/D' }}</span>
                                    </div>
                                    <div class="h-8 rounded-md bg-slate-100">
                                        <div
                                            class="flex h-8 items-center justify-end rounded-md bg-blue-600 px-2 text-xs font-semibold text-white"
                                            :style="{ width: geographicBarWidth(row.total) }"
                                        >
                                            {{ number2(row.total) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="overflow-hidden rounded-lg border" style="border-color: var(--stj-border);">
                            <div class="app-surface-soft app-border-soft border-b px-4 py-3">
                                <h3 class="app-text text-sm font-semibold">Detalle</h3>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-left text-sm">
                                    <thead class="app-surface-soft app-text-soft">
                                        <tr>
                                            <th class="px-3 py-2 font-semibold">Departamento</th>
                                            <th class="px-3 py-2 text-right font-semibold">Venta</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!geographicRows.length">
                                            <td colspan="2" class="app-muted px-3 py-6 text-center">Sin datos.</td>
                                        </tr>
                                        <tr v-for="row in geographicRows" :key="`geo-table-${row.id}-${row.department}`" class="app-border-soft border-b">
                                            <td class="px-3 py-2">{{ row.department }}</td>
                                            <td class="px-3 py-2 text-right">{{ number2(row.total) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                </div>

                <div v-else-if="activeTab === 'visits'" class="p-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="app-muted text-sm">Dashboard Analytics</p>
                            <h2 class="app-text mt-1 text-xl font-semibold">Visitas</h2>
                        </div>

                        <form class="grid gap-3 sm:grid-cols-3" @submit.prevent="loadVisits">
                            <label class="block">
                                <span class="app-muted text-sm">Inicio</span>
                                <input v-model="filters.startDate" type="date" class="stj-dashboard-input mt-1">
                            </label>
                            <label class="block">
                                <span class="app-muted text-sm">Fin</span>
                                <input v-model="filters.endDate" type="date" class="stj-dashboard-input mt-1">
                            </label>
                            <button type="submit" class="mt-6 h-10 rounded-md app-primary px-4 text-sm font-semibold transition hover:opacity-90">
                                Generar
                            </button>
                        </form>
                    </div>

                    <div class="app-border-soft mt-5 overflow-x-auto border-b">
                        <nav class="flex min-w-max" aria-label="Visitas tabs">
                            <button
                                v-for="tab in visitsTabs"
                                :key="tab.id"
                                type="button"
                                :class="[
                                    'border-b-2 px-4 py-3 text-sm font-medium transition',
                                    activeVisitsTab === tab.id
                                        ? 'border-blue-500 app-text'
                                        : 'border-transparent app-primary-text hover:bg-blue-50/10',
                                ]"
                                @click="activeVisitsTab = tab.id"
                            >
                                {{ tab.label }}
                            </button>
                        </nav>
                    </div>

                    <form
                        v-if="activeVisitsTab !== 'general'"
                        class="app-surface-soft app-border mt-5 grid gap-3 rounded-md border p-4 sm:grid-cols-3"
                        @submit.prevent="loadVisits"
                    >
                        <label class="block">
                            <span class="app-muted text-sm">Rango anterior inicio</span>
                            <input v-model="visitsPreviousFilters.startDate" type="date" class="stj-dashboard-input mt-1">
                        </label>
                        <label class="block">
                            <span class="app-muted text-sm">Rango anterior fin</span>
                            <input v-model="visitsPreviousFilters.endDate" type="date" class="stj-dashboard-input mt-1">
                        </label>
                        <button type="submit" class="mt-6 h-10 rounded-md app-primary px-4 text-sm font-semibold transition hover:opacity-90">
                            Comparar
                        </button>
                    </form>

                    <div v-if="visitsError" class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ visitsError }}
                    </div>

                    <div class="relative mt-5 min-h-[360px] overflow-x-auto pt-10">
                        <div v-if="visitsLoading" class="app-muted absolute inset-0 z-10 flex items-center justify-center bg-white/70 text-sm">
                            Cargando visitas...
                        </div>

                        <div class="absolute right-3 top-0 z-20">
                            <button
                                type="button"
                                class="stj-chart-menu-button"
                                aria-label="Descargar grafico de visitas"
                                :aria-expanded="visitsExportMenuOpen"
                                @click="visitsExportMenuOpen = !visitsExportMenuOpen"
                            >
                                <span></span>
                                <span></span>
                                <span></span>
                            </button>
                            <div v-if="visitsExportMenuOpen" class="stj-chart-menu">
                                <button type="button" @click="exportVisitsPng">Descargar PNG</button>
                                <button type="button" @click="exportVisitsCsv">Descargar CSV</button>
                                <button type="button" @click="exportVisitsSvg">Descargar SVG</button>
                            </div>
                        </div>

                        <svg
                            ref="visitsChartSvg"
                            class="min-w-[760px]"
                            :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
                            role="img"
                            aria-label="Grafico de visitas"
                        >
                            <g v-for="tick in visitsYTicks" :key="tick">
                                <line
                                    :x1="padding.left"
                                    :x2="chartWidth - padding.right"
                                    :y1="visitsYFor(tick)"
                                    :y2="visitsYFor(tick)"
                                    stroke="var(--stj-border-soft)"
                                />
                                <text
                                    :x="padding.left - 10"
                                    :y="visitsYFor(tick) + 4"
                                    text-anchor="end"
                                    class="fill-current app-muted"
                                    font-size="12"
                                >
                                    {{ tick }}
                                </text>
                            </g>

                            <g v-for="(date, index) in visitsData.categories" :key="date">
                                <text
                                    :x="visitsXFor(index)"
                                    :y="chartHeight - 18"
                                    text-anchor="middle"
                                    class="fill-current app-text-soft"
                                    font-size="12"
                                >
                                    {{ date.slice(5) }}
                                </text>
                            </g>

                            <g v-for="serie in visitsData.series" :key="serie.key">
                                <path
                                    :d="visitsLinePath(serie.data)"
                                    fill="none"
                                    :stroke="palette[serie.key]"
                                    stroke-width="3"
                                    :stroke-dasharray="serie.period === 'previous' ? '6 7' : '0'"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                                <circle
                                    v-for="(value, index) in serie.data"
                                    :key="`${serie.key}-${index}`"
                                    :cx="visitsXFor(index)"
                                    :cy="visitsYFor(value)"
                                    :r="visitsTooltip?.key === `visits-${index}` ? 5 : 3"
                                    :fill="palette[serie.key]"
                                    class="cursor-pointer transition"
                                    tabindex="0"
                                    @mouseenter="showVisitsTooltip(index, value)"
                                    @focus="showVisitsTooltip(index, value)"
                                    @mouseleave="hideVisitsTooltip"
                                    @blur="hideVisitsTooltip"
                                />
                            </g>

                            <g v-if="visitsTooltip" class="pointer-events-none">
                                <line
                                    :x1="visitsTooltip.x"
                                    :x2="visitsTooltip.x"
                                    :y1="padding.top"
                                    :y2="chartHeight - padding.bottom"
                                    stroke="var(--stj-border)"
                                    stroke-dasharray="4 4"
                                />
                                <rect
                                    :x="visitsTooltipX"
                                    :y="visitsTooltipY"
                                    :width="visitsTooltipWidth"
                                    :height="visitsTooltipHeight"
                                    rx="6"
                                    fill="var(--stj-surface)"
                                    stroke="var(--stj-border)"
                                />
                                <text
                                    :x="visitsTooltipX + 12"
                                    :y="visitsTooltipY + 22"
                                    class="fill-current app-text"
                                    font-size="13"
                                    font-weight="700"
                                >
                                    {{ visitsTooltip.date }}
                                </text>
                                <g v-for="(row, rowIndex) in visitsTooltip.rows" :key="row.key">
                                    <circle
                                        :cx="visitsTooltipX + 15"
                                        :cy="visitsTooltipY + 45 + rowIndex * 22"
                                        r="5"
                                        :fill="row.color"
                                    />
                                    <text
                                        :x="visitsTooltipX + 28"
                                        :y="visitsTooltipY + 49 + rowIndex * 22"
                                        class="fill-current app-text"
                                        font-size="12"
                                    >
                                        {{ row.label }}: {{ integer(row.value) }}
                                    </text>
                                </g>
                            </g>
                        </svg>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-3">
                        <div
                            v-for="serie in visitsData.series"
                            :key="serie.key"
                            class="app-surface-soft app-border flex items-center gap-2 rounded-md border px-3 py-2 text-sm"
                        >
                            <span class="h-3 w-3 rounded-full" :style="{ background: palette[serie.key] }"></span>
                            <span class="app-text-soft">{{ serie.label }}</span>
                        </div>
                    </div>

                    <div v-if="visitsData.mode === 'country'" class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div class="app-surface-soft app-border rounded-md border p-3">
                            <p class="app-muted text-xs">Visitas actuales</p>
                            <p class="app-text mt-1 text-lg font-semibold">{{ visitsTotals.visits || 0 }}</p>
                        </div>
                        <div class="app-surface-soft app-border rounded-md border p-3">
                            <p class="app-muted text-xs">Visitas anteriores</p>
                            <p class="app-text mt-1 text-lg font-semibold">{{ visitsTotals.previousVisits || 0 }}</p>
                        </div>
                    </div>

                    <div class="mt-5 overflow-x-auto">
                        <table v-if="visitsData.mode === 'general'" class="min-w-full text-left text-sm">
                            <thead class="app-surface-soft app-text-soft">
                                <tr>
                                    <th class="px-3 py-2 font-semibold">Pais</th>
                                    <th class="px-3 py-2 text-right font-semibold">Visitas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!visitsData.rows.length">
                                    <td colspan="2" class="app-muted px-3 py-6 text-center">Sin visitas para el rango seleccionado.</td>
                                </tr>
                                <tr v-for="row in visitsData.rows" :key="row.country" class="app-border-soft border-b">
                                    <td class="px-3 py-2">{{ row.country }}</td>
                                    <td class="px-3 py-2 text-right">{{ row.visits }}</td>
                                </tr>
                            </tbody>
                        </table>

                        <table v-else class="min-w-full text-left text-sm">
                            <thead class="app-surface-soft app-text-soft">
                                <tr>
                                    <th class="px-3 py-2 font-semibold">#</th>
                                    <th class="px-3 py-2 font-semibold">Fecha actual</th>
                                    <th class="px-3 py-2 text-right font-semibold">Visitas actual</th>
                                    <th class="px-3 py-2 text-right font-semibold">Fecha anterior</th>
                                    <th class="px-3 py-2 text-right font-semibold">Visitas anterior</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!visitsData.rows.length">
                                    <td colspan="5" class="app-muted px-3 py-6 text-center">Sin datos para el rango seleccionado.</td>
                                </tr>
                                <tr v-for="row in visitsData.rows" :key="row.date" class="app-border-soft border-b">
                                    <td class="px-3 py-2">{{ row.index }}</td>
                                    <td class="px-3 py-2">{{ row.date }}</td>
                                    <td class="px-3 py-2 text-right">{{ row.visits }}</td>
                                    <td class="px-3 py-2 text-right">{{ row.previousDate }}</td>
                                    <td class="px-3 py-2 text-right">{{ row.previousVisits }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-else-if="activeTab === 'app'" class="p-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="app-muted text-sm">Dashboard Analytics</p>
                            <h2 class="app-text mt-1 text-xl font-semibold">APP</h2>
                            <p class="app-text-soft mt-2 text-sm">Instalaciones por plataforma</p>
                        </div>

                        <form class="flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="loadApp">
                            <label class="block">
                                <span class="app-muted text-sm">Año</span>
                                <select v-model.number="appFilters.year" class="stj-dashboard-input mt-1 min-w-[130px]">
                                    <option v-for="year in appData.years" :key="year" :value="year">{{ year }}</option>
                                </select>
                            </label>
                            <button type="submit" class="h-10 rounded-md app-primary px-4 text-sm font-semibold transition hover:opacity-90">
                                {{ appLoading ? 'Actualizando...' : 'Generar' }}
                            </button>
                        </form>
                    </div>

                    <div v-if="appError" class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ appError }}
                    </div>

                    <div class="relative mt-6 grid gap-6 lg:grid-cols-[1fr_360px]">
                        <div v-if="appLoading" class="app-muted absolute inset-0 z-10 flex items-center justify-center bg-white/70 text-sm">
                            Cargando APP...
                        </div>

                        <section class="overflow-hidden rounded-lg border p-5" style="border-color: var(--stj-border);">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="app-text text-sm font-semibold">Instalaciones por mes</h3>
                                    <p class="app-muted mt-1 text-xs">{{ appData.filters.year || appFilters.year }}</p>
                                </div>
                                <div class="flex flex-wrap gap-3">
                                    <div v-for="platform in appPlatforms" :key="platform.key" class="flex items-center gap-2 text-sm">
                                        <span class="h-3 w-3 rounded-full" :style="{ background: platform.color }"></span>
                                        <span class="app-text-soft">{{ platform.label }}</span>
                                    </div>
                                </div>
                            </div>

                            <div v-if="!appRows.length" class="app-muted mt-5 rounded-md border p-6 text-center text-sm" style="border-color: var(--stj-border);">
                                Sin instalaciones para el año seleccionado.
                            </div>

                            <div v-else class="mt-6 overflow-x-auto pb-2">
                                <div class="flex min-w-[760px] items-end gap-4 border-b px-2 pt-4" style="border-color: var(--stj-border-soft); min-height: 280px;">
                                    <div v-for="row in appRows" :key="row.month" class="flex flex-1 flex-col items-center justify-end gap-3">
                                        <div class="flex h-56 w-full items-end justify-center gap-1">
                                            <div
                                                v-for="platform in appPlatforms"
                                                :key="`${row.month}-${platform.key}`"
                                                class="group relative w-5 rounded-t-md transition hover:opacity-80"
                                                :style="{ height: appBarHeight(row[platform.key]), background: platform.color }"
                                            >
                                                <span class="app-surface app-border app-text pointer-events-none absolute bottom-full left-1/2 mb-2 hidden -translate-x-1/2 whitespace-nowrap rounded border px-2 py-1 text-xs shadow-sm group-hover:block">
                                                    {{ platform.label }}: {{ row[platform.key] || 0 }}
                                                </span>
                                            </div>
                                        </div>
                                        <span class="app-text-soft text-xs font-medium">{{ row.monthLabel }}</span>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="overflow-hidden rounded-lg border" style="border-color: var(--stj-border);">
                            <div class="app-surface-soft app-border-soft border-b px-4 py-3">
                                <h3 class="app-text text-sm font-semibold">Resumen</h3>
                            </div>

                            <div class="grid grid-cols-2 gap-3 p-4">
                                <div
                                    v-for="platform in appPlatforms"
                                    :key="`summary-${platform.key}`"
                                    class="rounded-md border p-3"
                                    style="border-color: var(--stj-border);"
                                >
                                    <div class="flex items-center gap-2">
                                        <span class="h-3 w-3 rounded-full" :style="{ background: platform.color }"></span>
                                        <p class="app-muted text-xs">{{ platform.label }}</p>
                                    </div>
                                    <p class="app-text mt-2 text-xl font-bold">{{ appData.summary[platform.key] || 0 }}</p>
                                </div>
                                <div class="rounded-md border p-3" style="border-color: var(--stj-border);">
                                    <p class="app-muted text-xs">Total</p>
                                    <p class="app-text mt-2 text-xl font-bold">{{ appData.summary.total || 0 }}</p>
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="min-w-full text-left text-sm">
                                    <thead class="app-surface-soft app-text-soft">
                                        <tr>
                                            <th class="px-3 py-2 font-semibold">Mes</th>
                                            <th
                                                v-for="platform in appPlatforms"
                                                :key="`head-${platform.key}`"
                                                class="px-3 py-2 text-right font-semibold"
                                            >
                                                {{ platform.label }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!appRows.length">
                                            <td :colspan="appPlatforms.length + 1" class="app-muted px-3 py-6 text-center">Sin datos.</td>
                                        </tr>
                                        <tr v-for="row in appRows" :key="`app-table-${row.month}`" class="app-border-soft border-b">
                                            <td class="px-3 py-2">{{ row.monthLabel }}</td>
                                            <td
                                                v-for="platform in appPlatforms"
                                                :key="`app-table-${row.month}-${platform.key}`"
                                                class="px-3 py-2 text-right"
                                            >
                                                {{ row[platform.key] || 0 }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>

<style scoped>
.stj-dashboard-input {
    min-height: 40px;
    width: 100%;
    border: 1px solid var(--stj-border);
    border-radius: 6px;
    background: var(--stj-surface);
    color: var(--stj-text);
    padding: 0 0.75rem;
    font-size: 0.875rem;
}

.stj-dashboard-input:focus {
    border-color: var(--stj-primary);
    outline: 3px solid var(--stj-primary-soft);
}

.stj-chart-menu-button {
    align-items: center;
    background: var(--stj-surface);
    border: 1px solid var(--stj-border);
    border-radius: 6px;
    color: var(--stj-text-soft);
    display: inline-flex;
    flex-direction: column;
    gap: 3px;
    height: 32px;
    justify-content: center;
    transition: border-color 0.15s ease, color 0.15s ease, background 0.15s ease;
    width: 32px;
}

.stj-chart-menu-button:hover,
.stj-chart-menu-button:focus {
    background: var(--stj-surface-soft);
    border-color: var(--stj-primary);
    color: var(--stj-text);
    outline: none;
}

.stj-chart-menu-button span {
    background: currentColor;
    border-radius: 999px;
    display: block;
    height: 2px;
    width: 16px;
}

.stj-chart-menu {
    background: var(--stj-surface);
    border: 1px solid var(--stj-border);
    border-radius: 6px;
    box-shadow: 0 18px 40px rgb(0 0 0 / 0.28);
    margin-top: 0.35rem;
    min-width: 150px;
    overflow: hidden;
    padding: 0.35rem;
    position: absolute;
    right: 0;
}

.stj-chart-menu button {
    border-radius: 4px;
    color: var(--stj-text-soft);
    display: block;
    font-size: 0.875rem;
    padding: 0.55rem 0.7rem;
    text-align: left;
    width: 100%;
}

.stj-chart-menu button:hover,
.stj-chart-menu button:focus {
    background: var(--stj-surface-soft);
    color: var(--stj-text);
    outline: none;
}

.stj-chart-legend-button {
    transition: border-color 0.15s ease, opacity 0.15s ease, transform 0.15s ease;
}

.stj-chart-legend-button:hover,
.stj-chart-legend-button:focus {
    border-color: var(--stj-primary);
    outline: none;
    transform: translateY(-1px);
}

.stj-chart-legend-button-hidden {
    opacity: 0.38;
}

.stj-satisfaction-title {
    background: var(--stj-surface-soft);
    border: 1px solid var(--stj-border-soft);
    color: var(--stj-text);
    font-size: 0.82rem;
    font-weight: 700;
    padding: 0.45rem;
    text-align: center;
    text-transform: uppercase;
}

.stj-satisfaction-table {
    border-collapse: collapse;
    font-size: 0.875rem;
    min-width: 100%;
    text-align: left;
}

.stj-satisfaction-table th {
    background: var(--stj-surface-soft);
    color: var(--stj-text-soft);
    font-weight: 700;
    padding: 0.75rem;
}

.stj-satisfaction-table td {
    border-bottom: 1px solid var(--stj-border-soft);
    color: var(--stj-text);
    padding: 0.5rem 0.75rem;
}

.stj-otif {
    align-items: center;
    display: inline-flex;
    gap: 0.35rem;
    justify-content: flex-end;
    white-space: nowrap;
}

.stj-otif::after {
    border-radius: 999px;
    content: '';
    display: inline-block;
    height: 0.78rem;
    width: 0.78rem;
}

.stj-otif-red::after {
    background: #ef4444;
}

.stj-otif-orange::after {
    background: #f59e0b;
}

.stj-otif-yellow::after {
    background: #facc15;
}

.stj-otif-green::after {
    background: #16a34a;
}

.stj-segment-cash {
    background: #dbeafe;
}

.stj-segment-card {
    background: #fef3c7;
}

.stj-segment-legend {
    border: 1px solid var(--stj-border);
    display: inline-block;
    height: 0.7rem;
    margin-right: 0.35rem;
    width: 1.25rem;
}
</style>
