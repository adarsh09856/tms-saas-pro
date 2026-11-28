import Component from '@glimmer/component';

// Ledger widgets can render in the console owner outside the Ledger engine.
export default class CurrencyAmountComponent extends Component {
    get isInr() {
        const currency = this.args.currency;
        return typeof currency === 'string' && currency.trim().toUpperCase() === 'INR';
    }
}
