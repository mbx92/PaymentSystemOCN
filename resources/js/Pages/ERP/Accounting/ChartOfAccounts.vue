<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

const props = defineProps({
  accounts: Array,
  filters: Object,
  types: Array,
});

const filters = reactive({
  q: props.filters?.q ?? '',
  type: props.filters?.type ?? '',
  status: props.filters?.status ?? '',
});

let timer;
watch(filters, (val) => {
  clearTimeout(timer);
  timer = setTimeout(() => {
    router.get(route('erp.accounting.coa'), val, {
      preserveState: true,
      replace: true,
    });
  }, 250);
}, { deep: true });
</script>

<template>
  <Head title="Accounting - CoA / Chart Of Account" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Accounting Workspace</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <h1 class="text-3xl font-bold tracking-tight">CoA / Chart Of Account</h1>
          <Link class="btn btn-ghost btn-sm" :href="route('erp.accounting')">Back</Link>
        </div>
        <p class="mt-2 text-sm text-base-content/70">Daftar chart of accounts untuk kebutuhan posting jurnal seluruh modul ERP.</p>
      </div>

      <div class="card bg-base-100 shadow">
        <div class="card-body">
          <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <div class="md:col-span-2">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Search</span></label>
              <input v-model="filters.q" type="text" class="input input-sm input-bordered w-full" placeholder="Kode / nama akun" />
            </div>
            <div>
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Type</span></label>
              <select v-model="filters.type" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option v-for="type in types" :key="type" :value="type">{{ type }}</option>
              </select>
            </div>
            <div>
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Status</span></label>
              <select v-model="filters.status" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option value="active">active</option>
                <option value="inactive">inactive</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="card bg-base-100 shadow">
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>Kode Akun</th>
                <th>Nama Akun</th>
                <th>Type</th>
                <th>Normal Balance</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="account in accounts" :key="account.id">
                <td class="font-mono text-xs">{{ account.code }}</td>
                <td class="font-semibold">{{ account.name }}</td>
                <td class="uppercase text-xs">{{ account.type }}</td>
                <td class="uppercase text-xs">{{ account.normal_balance }}</td>
                <td><StatusBadge :status="account.status" /></td>
              </tr>
              <tr v-if="!accounts?.length">
                <td colspan="5" class="py-8 text-center text-base-content/50">Tidak ada akun ditemukan.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

