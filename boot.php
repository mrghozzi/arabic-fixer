<?php

declare(strict_types=1);

use App\Helpers\Hooks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use MyAds\Plugins\ArabicFixer\ArabicFixerService;

require_once __DIR__ . '/src/ArabicStringRepair.php';
require_once __DIR__ . '/src/ArabicFixerService.php';

if (!function_exists('arabic_fixer_service')) {
    function arabic_fixer_service(): ArabicFixerService
    {
        static $instance = null;

        if (!$instance instanceof ArabicFixerService) {
            $instance = new ArabicFixerService();
        }

        return $instance;
    }
}

Route::middleware(['web', 'auth', 'admin'])->group(function () {
    Route::get('/admin/arabic-fixer', function () {
        $service = arabic_fixer_service();

        return view('arabic_fixer::index', [
            'catalog' => $service->catalog(),
            'pageSummary' => $service->pageSummary(),
            'previewPayload' => session('arabic_fixer_preview'),
            'reportPayload' => session('arabic_fixer_report'),
        ]);
    })->name('admin.arabic-fixer.index');

    Route::post('/admin/arabic-fixer/preview', function (Request $request) {
        $service = arabic_fixer_service();

        $validated = $request->validate([
            'preset' => ['nullable', 'string', Rule::in($service->presetKeys())],
            'tables' => ['nullable', 'array'],
            'tables.*' => ['string', Rule::in($service->tableKeys())],
            'convert_charset' => ['nullable', 'boolean'],
        ], [], [
            'preset' => 'نطاق الفحص',
            'tables' => 'الجداول',
            'tables.*' => 'الجدول',
            'convert_charset' => 'خيار تحويل الترميز',
        ]);

        try {
            $selection = $service->normalizeSelection(
                $validated['preset'] ?? 'all',
                $validated['tables'] ?? []
            );
            $convertCharset = $request->boolean('convert_charset');
            $preview = $service->preview($selection, $convertCharset);

            session(['arabic_fixer_preview_signature' => $preview['selection']['signature']]);

            return redirect()
                ->route('admin.arabic-fixer.index')
                ->withInput($request->except('_token'))
                ->with('arabic_fixer_preview', $preview);
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.arabic-fixer.index')
                ->withInput($request->except('_token'))
                ->with('error', 'حدث خطأ أثناء المعاينة: ' . $e->getMessage());
        }
    })->name('admin.arabic-fixer.preview');

    Route::post('/admin/arabic-fixer/run', function (Request $request) {
        $service = arabic_fixer_service();

        $validated = $request->validate([
            'preset' => ['nullable', 'string', Rule::in($service->presetKeys())],
            'tables' => ['nullable', 'array'],
            'tables.*' => ['string', Rule::in($service->tableKeys())],
            'convert_charset' => ['nullable', 'boolean'],
            'backup_ack' => ['accepted'],
            'preview_signature' => ['required', 'string'],
        ], [
            'backup_ack.accepted' => 'يجب تأكيد وجود نسخة احتياطية قبل تنفيذ الإصلاح.',
            'preview_signature.required' => 'قم بتشغيل المعاينة أولاً قبل تنفيذ الإصلاح.',
        ], [
            'preset' => 'نطاق الفحص',
            'tables' => 'الجداول',
            'tables.*' => 'الجدول',
            'convert_charset' => 'خيار تحويل الترميز',
            'backup_ack' => 'تأكيد النسخة الاحتياطية',
            'preview_signature' => 'بصمة المعاينة',
        ]);

        try {
            $selection = $service->normalizeSelection(
                $validated['preset'] ?? 'all',
                $validated['tables'] ?? []
            );
            $convertCharset = $request->boolean('convert_charset');
            $expectedSignature = $service->selectionSignature($selection['tables'], $convertCharset);
            $storedSignature = (string) session('arabic_fixer_preview_signature', '');

            if (
                !hash_equals($expectedSignature, (string) $validated['preview_signature'])
                || $storedSignature === ''
                || !hash_equals($storedSignature, $expectedSignature)
            ) {
                throw ValidationException::withMessages([
                    'preview_signature' => 'قم بتشغيل معاينة جديدة بنفس الإعدادات قبل تنفيذ الإصلاح.',
                ]);
            }

            $report = $service->run($selection, $convertCharset);
            session()->forget('arabic_fixer_preview_signature');

            $success = $report['summary']['updated_fields'] > 0
                ? sprintf(
                    'تم إصلاح %d حقل عبر %d سجل بنجاح.',
                    (int) $report['summary']['updated_fields'],
                    (int) $report['summary']['updated_rows']
                )
                : 'لم يتم العثور على حقول جديدة قابلة للإصلاح أثناء التنفيذ.';

            return redirect()
                ->route('admin.arabic-fixer.index')
                ->with('success', $success)
                ->with('arabic_fixer_report', $report);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.arabic-fixer.index')
                ->withInput($request->except('_token'))
                ->with('error', 'تعذر تنفيذ الإصلاح: ' . $e->getMessage());
        }
    })->name('admin.arabic-fixer.run');
});

View::addNamespace('arabic_fixer', __DIR__ . '/views');

Hooks::add_action('admin_sidebar_menu', function (): void {
    $url = route('admin.arabic-fixer.index');
    $isActive = request()->routeIs('admin.arabic-fixer.*');
    $linkClass = $isActive ? 'nxl-link active' : 'nxl-link';

    echo '<li class="nxl-item">'
        . '<a href="' . e($url) . '" class="' . e($linkClass) . '">'
        . '<span class="nxl-micon"><i class="feather-tool"></i></span>'
        . '<span class="nxl-mtext">إصلاح النصوص العربية</span>'
        . '</a>'
        . '</li>';
});
