<?php

namespace App\Actions;

use App\Models\PartnerAgreement;
use App\Models\PartnerCompany;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class SavePartnerAgreement
{
    /** @param array<string, mixed> $input */
    public function handle(User $user, int $companyId, array $input, ?UploadedFile $document = null, ?int $agreementId = null): PartnerAgreement
    {
        Gate::forUser($user)->authorize('create', PartnerAgreement::class);
        $storedPath = null;

        try {
            return DB::transaction(function () use ($user, $companyId, $input, $document, $agreementId, &$storedPath): PartnerAgreement {
                $company = PartnerCompany::query()->lockForUpdate()->findOrFail($companyId);
                $agreement = $agreementId ? $company->agreements()->lockForUpdate()->findOrFail($agreementId) : new PartnerAgreement;

                if ($agreement->exists) {
                    Gate::forUser($user)->authorize('update', $agreement);
                }

                $data = Validator::make([...$input, 'document' => $document], [
                    'reference_number' => ['required', 'string', 'max:150', Rule::unique('partner_agreements')->where('partner_company_id', $company->id)->ignore($agreement->id)],
                    'kind' => ['required', Rule::in(['mou', 'moa'])],
                    'signed_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:expires_on'],
                    'starts_on' => ['required', 'date_format:Y-m-d'],
                    'expires_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
                    'notes' => ['nullable', 'string', 'max:3000'],
                    'is_active' => ['required', 'boolean'],
                    'document' => [$agreement->document_path ? 'nullable' : 'required', 'file', 'mimes:pdf', 'extensions:pdf', 'max:10240'],
                ], [
                    'document.required' => 'Unggah dokumen kerja sama dalam format PDF.',
                    'document.mimes' => 'Dokumen kerja sama harus berupa PDF.',
                    'document.max' => 'Ukuran PDF maksimal 10 MB.',
                    'expires_on.after_or_equal' => 'Masa berlaku harus berakhir pada atau setelah tanggal mulai.',
                ])->validate();
                unset($data['document']);
                $oldPath = $agreement->document_path;

                if ($document) {
                    $storedPath = $document->store('partner-agreements/'.$company->id, 'local');

                    if (! is_string($storedPath)) {
                        throw ValidationException::withMessages(['document' => 'Dokumen gagal disimpan. Coba unggah kembali.']);
                    }

                    $data['document_path'] = $storedPath;
                }

                $agreement->fill([...$data, 'partner_company_id' => $company->id])->save();

                if ($storedPath && $oldPath) {
                    DB::afterCommit(fn () => Storage::disk('local')->delete($oldPath));
                }

                return $agreement;
            });
        } catch (Throwable $exception) {
            if (is_string($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }
    }
}
