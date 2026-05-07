<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
  uoms: Array,
  conversions: Array,
});

const uomForm = useForm({
  code: '',
  name: '',
  status: 'active',
});

const conversionForm = useForm({
  from_uom_id: '',
  to_uom_id: '',
  multiplier: 1,
});

const submitUom = () => {
  uomForm.status = uomForm.status === 'active' ? 'active' : 'inactive';
  uomForm.post(route('erp.inventory.uoms.store'), {
    preserveScroll: true,
    onSuccess: () => uomForm.reset(),
  });
};

const submitConversion = () => {
  conversionForm.post(route('erp.inventory.uom-conversions.store'), {
    preserveScroll: true,
    onSuccess: () => conversionForm.reset(),
  });
};
</script>

<template>
  <Head title="Inventory - UoM & Konversi" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Inventory Workspace</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <h1 class="text-3xl font-bold tracking-tight">UoM & Konversi</h1>
          <Link class="btn btn-ghost btn-sm" :href="route('erp.inventory')">Back</Link>
        </div>
        <p class="mt-2 text-sm text-base-content/70">Kelola satuan produk dan hubungan konversi antar satuan untuk transaksi inventory.</p>
      </div>
      <div class="grid gap-4 lg:grid-cols-2">
        <div class="card bg-base-100 shadow">
          <div class="card-body space-y-3">
            <h2 class="card-title text-lg">Tambah UoM</h2>
            <input v-model="uomForm.code" class="input input-bordered" placeholder="Code (pcs, pack, dus)" />
            <input v-model="uomForm.name" class="input input-bordered" placeholder="Nama satuan" />
            <label class="label cursor-pointer justify-start gap-3 rounded-lg border border-base-300 px-3">
              <input
                :checked="uomForm.status === 'active'"
                type="checkbox"
                class="toggle toggle-success"
                @change="uomForm.status = $event.target.checked ? 'active' : 'inactive'"
              />
              <span class="label-text">{{ uomForm.status === 'active' ? 'Active' : 'Inactive' }}</span>
            </label>
            <button class="btn btn-primary" @click="submitUom">Simpan UoM</button>
          </div>
        </div>

        <div class="card bg-base-100 shadow">
          <div class="card-body space-y-3">
            <h2 class="card-title text-lg">Tambah Konversi UoM</h2>
            <select v-model="conversionForm.from_uom_id" class="select select-bordered">
              <option value="">From UoM</option>
              <option v-for="uom in uoms" :key="uom.id" :value="uom.id">{{ uom.code }} - {{ uom.name }}</option>
            </select>
            <select v-model="conversionForm.to_uom_id" class="select select-bordered">
              <option value="">To UoM</option>
              <option v-for="uom in uoms" :key="`to-${uom.id}`" :value="uom.id">{{ uom.code }} - {{ uom.name }}</option>
            </select>
            <input v-model.number="conversionForm.multiplier" type="number" min="0.0001" step="0.0001" class="input input-bordered" placeholder="Multiplier" />
            <button class="btn btn-primary" @click="submitConversion">Simpan Konversi</button>
          </div>
        </div>
      </div>

      <div class="card bg-base-100 shadow">
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead><tr><th>Code</th><th>Nama</th><th>Status</th></tr></thead>
            <tbody>
              <tr v-for="uom in uoms" :key="uom.id">
                <td class="font-mono text-xs">{{ uom.code }}</td>
                <td class="font-semibold">{{ uom.name }}</td>
                <td><StatusBadge :status="uom.status" /></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card bg-base-100 shadow">
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead><tr><th>From</th><th>To</th><th>Multiplier</th></tr></thead>
            <tbody>
              <tr v-for="conversion in conversions" :key="conversion.id">
                <td>{{ conversion.from_uom?.code }} - {{ conversion.from_uom?.name }}</td>
                <td>{{ conversion.to_uom?.code }} - {{ conversion.to_uom?.name }}</td>
                <td>{{ conversion.multiplier }}</td>
              </tr>
              <tr v-if="conversions.length === 0"><td colspan="3" class="text-center text-base-content/50">Belum ada konversi.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
