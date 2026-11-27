import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';

const STATUSES = [
    { value: 'new', label: 'New' },
    { value: 'contacted', label: 'Contacted' },
    { value: 'closed', label: 'Closed' },
];

export default class AdminWalkthroughRequestsComponent extends Component {
    @service fetch;
    @service notifications;

    statuses = STATUSES;
    @tracked rows = [];
    @tracked page = 1;
    @tracked lastPage = 1;
    @tracked total = 0;
    @tracked statusFilter = '';
    @tracked loading = true;
    @tracked errorMessage = '';
    @tracked successMessage = '';
    @tracked updatingId = null;

    constructor() {
        super(...arguments);
        this.load();
    }

    @action async load(page = this.page) {
        this.loading = true;
        this.errorMessage = '';
        this.successMessage = '';
        try {
            const query = new URLSearchParams({ page: String(page), per_page: '20' });
            if (this.statusFilter) query.set('status', this.statusFilter);
            const result = await this.fetch.get(`platform/walkthrough-requests?${query.toString()}`);
            this.rows = result?.data ?? [];
            this.page = result?.current_page ?? page;
            this.lastPage = result?.last_page ?? 1;
            this.total = result?.total ?? this.rows.length;
        } catch (error) {
            this.errorMessage = 'Walkthrough requests could not be loaded. Try again.';
            this.notifications.serverError(error);
        } finally {
            this.loading = false;
        }
    }

    @action setStatusFilter(event) {
        this.statusFilter = event.target.value;
        this.page = 1;
        this.load(1);
    }

    @action previousPage() {
        if (this.page > 1) this.load(this.page - 1);
    }

    @action nextPage() {
        if (this.page < this.lastPage) this.load(this.page + 1);
    }

    @action async updateStatus(row, event) {
        const status = event.target.value;
        if (status === row.status) return;
        this.updatingId = row.id;
        this.errorMessage = '';
        this.successMessage = '';
        try {
            const result = await this.fetch.patch(`platform/walkthrough-requests/${row.id}/status`, { status });
            const updated = result?.request;
            if (!updated) throw new Error('The updated request was not returned. Refresh and try again.');
            this.rows = this.rows.map((item) => (item.id === row.id ? updated : item));
            this.successMessage = `Request status updated to ${STATUSES.find((item) => item.value === updated.status)?.label ?? updated.status}.`;
            this.notifications.success(this.successMessage);
        } catch (error) {
            event.target.value = row.status;
            this.errorMessage = 'The request status could not be updated. Try again.';
            this.notifications.serverError(error);
        } finally {
            this.updatingId = null;
        }
    }
}
