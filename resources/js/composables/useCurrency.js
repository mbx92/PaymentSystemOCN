export function useCurrency() {
    const format = (value) => {
        if (value === null || value === undefined) return 'Rp 0';
        return 'Rp ' + Number(value).toLocaleString('id-ID');
    };

    const parse = (value) => {
        if (!value) return 0;
        return Number(String(value).replace(/\./g, '').replace(/,/g, '.'));
    };

    const formatInput = (value) => {
        const num = parse(value);
        if (isNaN(num)) return '';
        return num.toLocaleString('id-ID');
    };

    return { format, parse, formatInput };
}
