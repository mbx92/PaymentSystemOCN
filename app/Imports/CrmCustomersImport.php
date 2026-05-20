<?php

namespace App\Imports;

use App\ERP\CRM\Models\CrmCustomer;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CrmCustomersImport implements ToCollection, WithHeadingRow
{
    public int $imported = 0;

    /** @var list<array{row: int, message: string}> */
    public array $errors = [];

    public function collection(Collection $rows): void
    {
        $line = 1;

        foreach ($rows as $row) {
            $line++;
            $data = $this->normalizeRow($row);
            if ($this->isEmptyRow($data)) {
                continue;
            }

            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '') {
                $this->errors[] = ['row' => $line, 'message' => 'Kolom name wajib diisi.'];

                continue;
            }

            $code = strtoupper(trim((string) ($data['code'] ?? '')));
            if ($code === '') {
                $code = null;
            }

            $customer = $code
                ? CrmCustomer::query()->where('code', $code)->first()
                : null;

            $company = $this->nullIfBlank($data['company'] ?? null);
            $email = strtolower((string) $this->nullIfBlank($data['email'] ?? null));
            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->errors[] = ['row' => $line, 'message' => "Customer \"{$name}\": email tidak valid."];

                continue;
            }
            if ($email === '') {
                $email = null;
            }

            if ($email) {
                $emailTaken = CrmCustomer::query()
                    ->where('email', $email)
                    ->when($customer, fn ($query) => $query->where('id', '!=', $customer->id))
                    ->exists();

                if ($emailTaken) {
                    $this->errors[] = ['row' => $line, 'message' => "Customer \"{$name}\": email sudah dipakai customer lain."];

                    continue;
                }
            }

            $picUserId = $this->resolvePicUserId($data);
            if (($data['pic_email'] ?? null) || ($data['pic_name'] ?? null)) {
                if ($picUserId === false) {
                    $this->errors[] = ['row' => $line, 'message' => "Customer \"{$name}\": PIC tidak ditemukan dari pic_email/pic_name."];

                    continue;
                }
            }

            if ($code) {
                $codeTaken = CrmCustomer::query()
                    ->where('code', $code)
                    ->when($customer, fn ($query) => $query->where('id', '!=', $customer->id))
                    ->exists();

                if ($codeTaken) {
                    $this->errors[] = ['row' => $line, 'message' => "Customer \"{$name}\": code sudah dipakai customer lain."];

                    continue;
                }
            }

            $payload = [
                'code' => $code ?? $this->generateCode(),
                'name' => $name,
                'company' => $company,
                'email' => $email,
                'phone' => $this->trimToLength($data['phone'] ?? null, 50),
                'address' => $this->nullIfBlank($data['address'] ?? null),
                'business_type' => $this->trimToLength($data['business_type'] ?? null, 60),
                'tax_id' => $this->trimToLength($data['tax_id'] ?? null, 50),
                'source' => $this->trimToLength($data['source'] ?? 'import_excel', 60) ?? 'import_excel',
                'pic_user_id' => $picUserId === false ? null : $picUserId,
                'is_active' => $this->toBool($data['is_active'] ?? true),
                'notes' => $this->nullIfBlank($data['notes'] ?? null),
            ];

            if ($customer) {
                $customer->update($payload);
            } else {
                CrmCustomer::query()->create($payload);
            }

            $this->imported++;
        }
    }

    /**
     * @param  Collection<int, mixed>|array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(Collection|array $row): array
    {
        $arr = is_array($row) ? $row : $row->toArray();
        $out = [];
        foreach ($arr as $key => $value) {
            if (is_int($key)) {
                continue;
            }

            $out[Str::slug((string) $key, '_')] = $value;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isEmptyRow(array $data): bool
    {
        foreach ($data as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    private function resolvePicUserId(array $data): int|false|null
    {
        $picEmail = strtolower(trim((string) ($data['pic_email'] ?? '')));
        if ($picEmail !== '') {
            $userId = User::query()->whereRaw('LOWER(email) = ?', [$picEmail])->value('id');

            return $userId ?: false;
        }

        $picName = trim((string) ($data['pic_name'] ?? ''));
        if ($picName !== '') {
            $userId = User::query()->where('name', $picName)->value('id');

            return $userId ?: false;
        }

        return null;
    }

    private function nullIfBlank(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function trimToLength(mixed $value, int $length): ?string
    {
        $value = $this->nullIfBlank($value);

        return $value ? Str::limit($value, $length, '') : null;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y', 'aktif', 'active'], true);
    }

    private function generateCode(): string
    {
        $last = DB::table('crm_customers')
            ->where('code', 'like', 'CUST-%')
            ->orderByDesc('code')
            ->value('code');

        $seq = 1;
        if ($last && preg_match('/CUST-(\d+)/', $last, $matches)) {
            $seq = (int) $matches[1] + 1;
        }

        return 'CUST-'.str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
