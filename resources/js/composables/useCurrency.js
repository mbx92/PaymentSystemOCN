export function useCurrency() {
    const format = (value) => {
        if (value === null || value === undefined) return 'Rp 0';
        return 'Rp ' + Number(value).toLocaleString('id-ID');
    };

    const parse = (value) => {
        if (value === '' || value === null || value === undefined) return 0;
        return Number(String(value).replace(/\./g, '').replace(/,/g, '.'));
    };

    const formatInput = (value) => {
        if (value === '' || value === null || value === undefined) return '';
        const num = typeof value === 'number' ? value : parse(value);
        if (!Number.isFinite(num) || num === 0) return '';
        return Math.round(num).toLocaleString('id-ID');
    };

    return { format, parse, formatInput };
}
