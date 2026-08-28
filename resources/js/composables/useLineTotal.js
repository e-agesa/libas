/**
 * Single source of truth for what a line item costs.
 *
 * custom line = (craftsmanship fee + fabric cost) x garment quantity
 *             + ridhaa quantity x ridhaa price
 *
 * Ridhaa is billed on its own quantity, not multiplied by the garment
 * quantity: it is a separate item written onto the line at invoice time.
 */
export function ridhaaTotal(item) {
    const qty = parseFloat(item?.ridhaa_qty) || 0;
    const price = parseFloat(item?.ridhaa_price) || 0;
    return qty * price;
}

export function lineItemTotal(item) {
    if (!item) return 0;
    const qty = parseFloat(item.quantity) || 1;

    if (item.item_type === 'collection') {
        return (parseFloat(item.unit_price) || 0) * qty;
    }

    const fee = parseFloat(item.craftsmanship_fee) || 0;
    const fabric = parseFloat(item.fabric_cost) || 0;
    return (fee + fabric) * qty + ridhaaTotal(item);
}

export function invoiceSubtotal(items) {
    return (items || []).reduce((sum, item) => sum + lineItemTotal(item), 0);
}
