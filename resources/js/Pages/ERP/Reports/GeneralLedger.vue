<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
  entries: Object,
});
</script>

<template>
  <Head title="General Ledger" />
  <AppLayout>
    <div class="py-6">
      <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-bold">General Ledger</h1>
        <div
          v-for="entry in entries.data"
          :key="entry.id"
          class="overflow-hidden bg-white shadow-sm sm:rounded-lg"
        >
          <div class="border-b px-6 py-4">
            <p class="font-semibold">{{ entry.entry_no }} - {{ entry.entry_date }}</p>
            <p class="text-sm text-gray-600">{{ entry.description }}</p>
          </div>
          <div class="px-6 py-4">
            <table class="w-full text-sm">
              <thead>
                <tr class="text-left text-gray-500">
                  <th class="py-2">Akun</th>
                  <th class="py-2">Debit</th>
                  <th class="py-2">Kredit</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="line in entry.lines" :key="line.id" class="border-t">
                  <td class="py-2">{{ line.account?.code }} - {{ line.account?.name }}</td>
                  <td class="py-2">{{ Number(line.debit).toLocaleString('id-ID') }}</td>
                  <td class="py-2">{{ Number(line.credit).toLocaleString('id-ID') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
