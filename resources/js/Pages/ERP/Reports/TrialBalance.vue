<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
  balances: Array,
});
</script>

<template>
  <Head title="Neraca Saldo" />
  <AppLayout>
    <div class="py-6">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <h1 class="mb-4 text-2xl font-bold">Neraca Saldo</h1>
        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
          <div class="p-6">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b text-left text-gray-500">
                  <th class="py-2">Kode</th>
                  <th class="py-2">Akun</th>
                  <th class="py-2">Debit</th>
                  <th class="py-2">Kredit</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in balances" :key="row.code" class="border-b">
                  <td class="py-2">{{ row.code }}</td>
                  <td class="py-2">{{ row.name }}</td>
                  <td class="py-2">{{ Number(row.debit_total).toLocaleString('id-ID') }}</td>
                  <td class="py-2">{{ Number(row.credit_total).toLocaleString('id-ID') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
