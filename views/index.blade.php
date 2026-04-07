@extends('admin::layouts.admin')

@section('title', 'إصلاح النصوص العربية')
@section('admin_shell_header_mode', 'hidden')

@php
    $tables = $catalog['tables'] ?? [];
    $presets = $catalog['presets'] ?? [];
    $availableTables = $catalog['available_tables'] ?? [];
    $activePayload = $reportPayload ?: $previewPayload;
    $selectedPreset = old('preset', $activePayload['selection']['preset'] ?? 'all');
    $selectedTables = old('tables', $activePayload['selection']['tables'] ?? $availableTables);
    $selectedTables = array_values(array_unique(array_intersect((array) $selectedTables, array_keys($tables))));
    $convertCharset = (bool) old('convert_charset', $activePayload['selection']['convert_charset'] ?? false);
    $previewSummary = $previewPayload['summary'] ?? [];
    $reportSummary = $reportPayload['summary'] ?? [];
    $presetMap = collect($presets)->mapWithKeys(fn ($preset, $key) => [$key => $preset['tables']])->all();
    $presetDescriptions = collect($presets)->mapWithKeys(fn ($preset, $key) => [$key => $preset['description']])->all();
@endphp

@section('content')
<div class="admin-page arabic-fixer-page">
    <section class="admin-hero">
        <div class="admin-hero__content">
            <ul class="admin-breadcrumb">
                <li><a href="{{ route('admin.index') }}">لوحة الإدارة</a></li>
                <li>الإضافات</li>
                <li>إصلاح النصوص العربية</li>
            </ul>
            <div class="admin-hero__eyebrow">Arabic Fixer</div>
            <h1 class="admin-hero__title">مساحة إصلاح النصوص العربية المعطوبة</h1>
            <p class="admin-hero__copy">
                افحص النصوص العربية المعطوبة داخل قاعدة البيانات، راجع الفروقات قبل التنفيذ، ثم شغّل الإصلاح
                ضمن نطاق محدد مع خطوة أمان صريحة للنسخة الاحتياطية.
            </p>
        </div>
        <div class="admin-hero__actions">
            <div class="admin-summary-grid w-100">
                <div class="admin-summary-card">
                    <span class="admin-summary-label">الجداول المتاحة</span>
                    <span class="admin-summary-value">{{ $pageSummary['available_tables_count'] ?? 0 }}</span>
                    <span class="admin-summary-meta">الجداول التي اكتشفتها الإضافة في هذا التثبيت</span>
                </div>
                <div class="admin-summary-card">
                    <span class="admin-summary-label">الأعمدة المرشحة</span>
                    <span class="admin-summary-value">{{ $pageSummary['candidate_columns_total'] ?? 0 }}</span>
                    <span class="admin-summary-meta">أعمدة نصية صالحة للفحص</span>
                </div>
                <div class="admin-summary-card">
                    <span class="admin-summary-label">آخر نطاق</span>
                    <span class="admin-summary-value">{{ $activePayload['selection']['preset_label'] ?? 'كل الجداول المدعومة' }}</span>
                    <span class="admin-summary-meta">{{ !empty($activePayload['selection']['convert_charset']) ? 'مع تحويل ترميز' : 'بدون تحويل ترميز' }}</span>
                </div>
            </div>
        </div>
    </section>

    @if(session('success'))
        <div class="alert alert-success shadow-sm mb-4">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger shadow-sm mb-4">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger shadow-sm mb-4">
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="admin-workspace-grid">
        <section class="admin-panel">
            <div class="admin-panel__header">
                <div>
                    <span class="admin-panel__eyebrow">إعدادات المعاينة</span>
                    <h2 class="admin-panel__title">اختر النطاق والجداول</h2>
                    <p class="admin-panel__copy mb-0">المعاينة لا تعدّل البيانات، لكنها تحفظ بصمة مطابقة للتنفيذ اللاحق.</p>
                </div>
            </div>
            <div class="admin-panel__body">
                <form action="{{ route('admin.arabic-fixer.preview') }}" method="POST">
                    @csrf
                    <div class="admin-toolbar-card mb-4">
                        <div class="flex-fill">
                            <label class="form-label fw-semibold mb-2" for="arabic_fixer_preset">نطاق الفحص</label>
                            <select class="form-select" id="arabic_fixer_preset" name="preset">
                                @foreach($presets as $presetKey => $preset)
                                    <option value="{{ $presetKey }}" @selected($selectedPreset === $presetKey)>{{ $preset['label'] }} ({{ $preset['table_count'] }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex-fill">
                            <div class="fw-semibold mb-1">الوصف</div>
                            <div class="text-muted small" id="arabic-fixer-preset-description">{{ $presetDescriptions[$selectedPreset] ?? '' }}</div>
                        </div>
                    </div>

                    <div class="arabic-fixer-presets">
                        @foreach($presets as $preset)
                            <button type="button" class="arabic-fixer-preset {{ $selectedPreset === $preset['key'] ? 'is-active' : '' }}" data-preset-card="{{ $preset['key'] }}">
                                <strong>{{ $preset['label'] }}</strong>
                                <span>{{ $preset['description'] }}</span>
                            </button>
                        @endforeach
                    </div>

                    <div class="arabic-fixer-table-grid mt-4">
                        @foreach($tables as $tableKey => $table)
                            <label class="arabic-fixer-table {{ $table['available'] ? '' : 'is-disabled' }}">
                                <input type="checkbox" name="tables[]" value="{{ $tableKey }}" class="form-check-input arabic-fixer-table-checkbox" @checked(in_array($tableKey, $selectedTables, true)) @disabled(!$table['available'])>
                                <span>
                                    <strong>{{ $table['label'] }}</strong>
                                    <small><code>{{ $tableKey }}</code> · {{ $table['available'] ? $table['candidate_column_count'] . ' عمود نصي' : 'غير موجود' }}</small>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <label class="arabic-fixer-toggle mt-4">
                        <input type="checkbox" name="convert_charset" value="1" @checked($convertCharset)>
                        <span>
                            <strong>تحويل charset/collation للجداول المحددة</strong>
                            <small>خيار اختياري. إذا لم تكن البيئة MySQL/MariaDB فسيتم تجاهله تلقائياً.</small>
                        </span>
                    </label>

                    <div class="d-flex flex-wrap gap-3 mt-4">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="arabic-fixer-select-preset">تطبيق النطاق</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="arabic-fixer-select-all">تحديد الكل</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="arabic-fixer-clear-all">إلغاء التحديد</button>
                        <button type="submit" class="btn btn-primary"><i class="feather-eye me-1"></i>تشغيل المعاينة</button>
                    </div>
                </form>
            </div>
        </section>

        <aside class="admin-panel">
            <div class="admin-panel__header">
                <div>
                    <span class="admin-panel__eyebrow">الأمان والتنفيذ</span>
                    <h2 class="admin-panel__title">شغّل التنفيذ فقط بعد المعاينة</h2>
                    <p class="admin-panel__copy mb-0">أي تغيير في النطاق أو الجداول يتطلب معاينة جديدة قبل التنفيذ.</p>
                </div>
            </div>
            <div class="admin-panel__body">
                <div class="admin-note-card arabic-fixer-note danger mb-3">
                    <span class="admin-note-label">تنبيه</span>
                    <span class="admin-note-copy">خذ نسخة احتياطية كاملة قبل تشغيل الأداة على قاعدة بيانات حيّة.</span>
                </div>

                @if($previewPayload)
                    <form action="{{ route('admin.arabic-fixer.run') }}" method="POST">
                        @csrf
                        <input type="hidden" name="preset" value="{{ $previewPayload['selection']['preset'] }}">
                        @foreach($previewPayload['selection']['tables'] as $tableKey)
                            <input type="hidden" name="tables[]" value="{{ $tableKey }}">
                        @endforeach
                        @if(!empty($previewPayload['selection']['convert_charset']))
                            <input type="hidden" name="convert_charset" value="1">
                        @endif
                        <input type="hidden" name="preview_signature" value="{{ $previewPayload['selection']['signature'] }}">

                        <div class="admin-summary-card mb-3">
                            <span class="admin-summary-label">المعاينة الحالية</span>
                            <span class="admin-summary-value">{{ $previewSummary['fields_detected'] ?? 0 }}</span>
                            <span class="admin-summary-meta">حقل مرشح للإصلاح</span>
                        </div>

                        <div class="form-check arabic-fixer-backup mb-3">
                            <input class="form-check-input" type="checkbox" value="1" id="arabic_fixer_backup_ack" name="backup_ack">
                            <label class="form-check-label" for="arabic_fixer_backup_ack">أؤكد وجود نسخة احتياطية حديثة.</label>
                        </div>

                        <button type="submit" class="btn btn-danger w-100" @disabled(($previewSummary['fields_detected'] ?? 0) === 0)>
                            <i class="feather-tool me-1"></i>تنفيذ الإصلاح
                        </button>
                    </form>
                @else
                    <div class="arabic-fixer-empty">
                        <i class="feather-eye"></i>
                        <h3>ابدأ بالمعاينة أولاً</h3>
                        <p>بعد المعاينة ستظهر هنا استمارة التنفيذ مع بصمة مطابقة للإعدادات الحالية.</p>
                    </div>
                @endif
            </div>
        </aside>
    </div>

    @if($previewPayload)
        <section class="admin-panel">
            <div class="admin-panel__header">
                <div>
                    <span class="admin-panel__eyebrow">نتائج المعاينة</span>
                    <h2 class="admin-panel__title">الفروقات المكتشفة</h2>
                    <p class="admin-panel__copy mb-0">تم فحص {{ $previewSummary['tables_scanned'] ?? 0 }} جدول و{{ $previewSummary['rows_scanned'] ?? 0 }} سجل.</p>
                </div>
            </div>
            <div class="admin-panel__body">
                @foreach(['warnings' => 'warning', 'errors' => 'danger'] as $type => $alertClass)
                    @if(!empty($previewPayload[$type]))
                        <div class="alert alert-{{ $alertClass }} shadow-sm mb-4">
                            <ul class="mb-0 ps-3">
                                @foreach($previewPayload[$type] as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach

                @if(($previewSummary['fields_detected'] ?? 0) === 0)
                    <div class="arabic-fixer-empty compact">
                        <i class="feather-check-circle"></i>
                        <h3>لا توجد نتائج قابلة للإصلاح</h3>
                        <p>المعاينة الحالية لم تعثر على نصوص عربية معطوبة داخل الجداول المحددة.</p>
                    </div>
                @else
                    <div class="accordion" id="arabicFixerPreviewAccordion">
                        @foreach($previewPayload['groups'] ?? [] as $tableKey => $group)
                            <div class="accordion-item arabic-fixer-accordion-item">
                                <h2 class="accordion-header" id="preview-heading-{{ $loop->index }}">
                                    <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#preview-collapse-{{ $loop->index }}">
                                        <strong>{{ $group['meta']['label'] }}</strong>
                                        <span class="ms-2 text-muted small"><code>{{ $tableKey }}</code> · {{ $group['count'] }} مثال</span>
                                    </button>
                                </h2>
                                <div id="preview-collapse-{{ $loop->index }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#arabicFixerPreviewAccordion">
                                    <div class="accordion-body">
                                        <div class="table-responsive">
                                            <table class="table arabic-fixer-table-results">
                                                <thead>
                                                    <tr><th>العمود</th><th>المعرّف</th><th>قبل الإصلاح</th><th>بعد الإصلاح</th></tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($group['items'] as $item)
                                                        <tr>
                                                            <td data-label="العمود"><code>{{ $item['column'] }}</code></td>
                                                            <td data-label="المعرّف">{{ $item['identifier'] }}</td>
                                                            <td data-label="قبل الإصلاح"><div class="arabic-fixer-before" dir="ltr">{{ $item['before'] }}</div></td>
                                                            <td data-label="بعد الإصلاح"><div class="arabic-fixer-after" dir="rtl">{{ $item['after'] }}</div></td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    @endif

    @if($reportPayload)
        <section class="admin-panel">
            <div class="admin-panel__header">
                <div>
                    <span class="admin-panel__eyebrow">تقرير التنفيذ</span>
                    <h2 class="admin-panel__title">آخر عملية</h2>
                    <p class="admin-panel__copy mb-0">تم تحديث {{ $reportSummary['updated_fields'] ?? 0 }} حقل عبر {{ $reportSummary['updated_rows'] ?? 0 }} سجل.</p>
                </div>
            </div>
            <div class="admin-panel__body">
                <div class="admin-summary-grid mb-4">
                    <div class="admin-summary-card"><span class="admin-summary-label">حقول تم تحديثها</span><span class="admin-summary-value">{{ $reportSummary['updated_fields'] ?? 0 }}</span></div>
                    <div class="admin-summary-card"><span class="admin-summary-label">سجلات تم تحديثها</span><span class="admin-summary-value">{{ $reportSummary['updated_rows'] ?? 0 }}</span></div>
                    <div class="admin-summary-card"><span class="admin-summary-label">جداول محولة الترميز</span><span class="admin-summary-value">{{ $reportSummary['charset_tables_converted'] ?? 0 }}</span></div>
                </div>
                @foreach(['warnings' => 'warning', 'errors' => 'danger'] as $type => $alertClass)
                    @if(!empty($reportPayload[$type]))
                        <div class="alert alert-{{ $alertClass }} shadow-sm mb-4">
                            <ul class="mb-0 ps-3">
                                @foreach($reportPayload[$type] as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach
            </div>
        </section>
    @endif
</div>

<style>
    .arabic-fixer-page{gap:1.5rem}.arabic-fixer-presets,.arabic-fixer-table-grid{display:grid;gap:1rem}.arabic-fixer-presets{grid-template-columns:repeat(auto-fit,minmax(180px,1fr))}.arabic-fixer-table-grid{grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}
    .arabic-fixer-preset,.arabic-fixer-table,.arabic-fixer-toggle{background:var(--admin-premium-surface);border:1px solid var(--admin-premium-border);border-radius:20px;color:var(--admin-premium-text);display:flex;gap:.85rem;padding:1rem;text-align:start}
    .arabic-fixer-preset{flex-direction:column}.arabic-fixer-preset.is-active,.arabic-fixer-preset:hover{border-color:var(--admin-premium-border-strong);box-shadow:var(--admin-premium-shadow-soft)}.arabic-fixer-preset span,.arabic-fixer-table small,.arabic-fixer-toggle small{color:var(--admin-premium-muted);display:block;line-height:1.7}
    .arabic-fixer-table.is-disabled{opacity:.55}.arabic-fixer-note.danger{border-color:rgba(239,68,68,.2)}.arabic-fixer-backup{background:var(--admin-premium-surface-alt);border-radius:16px;padding:.9rem 1rem}
    .arabic-fixer-empty{align-items:center;background:var(--admin-premium-surface);border:1px dashed var(--admin-premium-border-strong);border-radius:24px;display:flex;flex-direction:column;gap:.75rem;justify-content:center;min-height:200px;padding:2rem;text-align:center}.arabic-fixer-empty.compact{min-height:160px}
    .arabic-fixer-accordion-item{border:1px solid var(--admin-premium-border);border-radius:18px;margin-bottom:1rem;overflow:hidden}.arabic-fixer-before,.arabic-fixer-after{border-radius:16px;line-height:1.7;padding:.85rem 1rem;word-break:break-word}
    .arabic-fixer-before{background:rgba(239,68,68,.08);color:#b91c1c;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace;font-size:.82rem}.arabic-fixer-after{background:rgba(34,197,94,.1);color:#166534;font-weight:700}
    @media (max-width:767.98px){.arabic-fixer-table-results thead{display:none}.arabic-fixer-table-results tbody,.arabic-fixer-table-results tr,.arabic-fixer-table-results td{display:block;width:100%}.arabic-fixer-table-results td{border:0;padding:.45rem 0}.arabic-fixer-table-results td::before{content:attr(data-label);display:block;font-size:.78rem;font-weight:700;margin-bottom:.35rem}}
</style>

<script>
document.addEventListener('DOMContentLoaded',function(){const s=document.getElementById('arabic_fixer_preset');const d=document.getElementById('arabic-fixer-preset-description');const c=[...document.querySelectorAll('.arabic-fixer-table-checkbox')];const cards=[...document.querySelectorAll('[data-preset-card]')];const map=@json($presetMap, JSON_UNESCAPED_UNICODE);const desc=@json($presetDescriptions, JSON_UNESCAPED_UNICODE);function applyPreset(key){(map[key]||[]);c.forEach(x=>{if(!x.disabled)x.checked=(map[key]||[]).includes(x.value)});cards.forEach(x=>x.classList.toggle('is-active',x.dataset.presetCard===key));if(d)d.textContent=desc[key]||''}cards.forEach(x=>x.addEventListener('click',function(){s.value=this.dataset.presetCard;applyPreset(this.dataset.presetCard)}));if(s)s.addEventListener('change',function(){applyPreset(this.value)});document.getElementById('arabic-fixer-select-preset')?.addEventListener('click',()=>applyPreset(s.value));document.getElementById('arabic-fixer-select-all')?.addEventListener('click',()=>c.forEach(x=>{if(!x.disabled)x.checked=true}));document.getElementById('arabic-fixer-clear-all')?.addEventListener('click',()=>c.forEach(x=>{if(!x.disabled)x.checked=false}))});
</script>
@endsection
