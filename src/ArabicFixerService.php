<?php

declare(strict_types=1);

namespace MyAds\Plugins\ArabicFixer;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class ArabicFixerService
{
    private const EXAMPLE_LIMIT = 200;

    private const TABLES = [
        'setting' => ['label' => 'الإعدادات العامة', 'pk' => 'id'],
        'options' => ['label' => 'الخيارات والمحتوى المخزن', 'pk' => 'id'],
        'menu' => ['label' => 'القوائم', 'pk' => 'id_m'],
        'pages' => ['label' => 'الصفحات', 'pk' => 'id'],
        'status' => ['label' => 'منشورات المجتمع', 'pk' => 'id'],
        'forum' => ['label' => 'مواضيع المنتدى', 'pk' => 'id'],
        'f_cat' => ['label' => 'أقسام المنتدى', 'pk' => 'id'],
        'f_comment' => ['label' => 'تعليقات المنتدى', 'pk' => 'id'],
        'users' => ['label' => 'الأعضاء', 'pk' => 'id'],
        'messages' => ['label' => 'الرسائل الخاصة', 'pk' => 'id_msg'],
        'notif' => ['label' => 'الإشعارات', 'pk' => 'id'],
        'report' => ['label' => 'البلاغات', 'pk' => 'id'],
        'ads' => ['label' => 'إعلانات الموقع', 'pk' => 'id'],
        'banner' => ['label' => 'إعلانات البانر', 'pk' => 'id'],
        'link' => ['label' => 'الإعلانات النصية', 'pk' => 'id'],
        'smart_ads' => ['label' => 'الإعلانات الذكية', 'pk' => 'id'],
        'visits' => ['label' => 'التبادل والزيارات', 'pk' => 'id'],
        'directory' => ['label' => 'دليل المواقع', 'pk' => 'id'],
        'cat_dir' => ['label' => 'أقسام الدليل', 'pk' => 'id'],
        'news' => ['label' => 'الأخبار', 'pk' => 'id'],
        'order_requests' => ['label' => 'طلبات الخدمات', 'pk' => 'id'],
    ];

    private const PRESETS = [
        'all' => [
            'label' => 'كل الجداول المدعومة',
            'description' => 'يفحص كل الجداول المتاحة التي تعرفها الإضافة.',
            'icon' => 'fa-solid fa-layer-group',
            'tables' => [
                'setting', 'options', 'menu', 'pages', 'status', 'forum', 'f_cat', 'f_comment',
                'users', 'messages', 'notif', 'report', 'ads', 'banner', 'link', 'smart_ads',
                'visits', 'directory', 'cat_dir', 'news', 'order_requests',
            ],
        ],
        'settings' => [
            'label' => 'الإعدادات والخيارات',
            'description' => 'الإعدادات العامة، الخيارات، القوائم، والصفحات.',
            'icon' => 'fa-solid fa-sliders',
            'tables' => ['setting', 'options', 'menu', 'pages'],
        ],
        'community' => [
            'label' => 'المجتمع والمنتدى',
            'description' => 'المنشورات، المنتدى، الأقسام، التعليقات، والبلاغات.',
            'icon' => 'fa-solid fa-users',
            'tables' => ['status', 'forum', 'f_cat', 'f_comment', 'report'],
        ],
        'members' => [
            'label' => 'الرسائل والإشعارات والأعضاء',
            'description' => 'بيانات الأعضاء، الرسائل الخاصة، والإشعارات.',
            'icon' => 'fa-solid fa-envelope-open-text',
            'tables' => ['users', 'messages', 'notif'],
        ],
        'ads_directory' => [
            'label' => 'الإعلانات والدليل',
            'description' => 'الإعلانات المختلفة، الزيارات، والدليل.',
            'icon' => 'fa-solid fa-rectangle-ad',
            'tables' => ['ads', 'banner', 'link', 'smart_ads', 'visits', 'directory', 'cat_dir'],
        ],
        'content' => [
            'label' => 'الأخبار والمتجر والصفحات',
            'description' => 'الأخبار والطلبات والبيانات النصية المخزنة في الخيارات.',
            'icon' => 'fa-solid fa-newspaper',
            'tables' => ['news', 'order_requests', 'pages', 'options'],
        ],
    ];

    private const EXCLUDED_COLUMNS = [
        'id', 'id_m', 'id_msg', 'uid', 'ruid', 'sid', 'pid', 'tp_id', 'tid', 'cat', 'sub', 'reply',
        'statu', 'state', 'status', 'best_offer_id', 'vu', 'clik', 'pts', 'nvu', 'nlink', 'nsmart',
        'budget', 'avg_rating', 'ordercat', 'o_parent', 'o_order', 'o_type', 'o_mode', 'close',
        'e_links', 'online', 'ucheck', 'pass', 'password', 'email', 'remember_token', 'token',
        'public_uid', 'url', 'landing_url', 'nurl', 'avatar', 'cover', 'img', 'image', 'source_image',
        'logo', 'icon', 'file', 'attachment_path', 'attachment_name', 'attachment_size', 'slug', 'sho',
        'dir', 'styles', 'lang', 'timezone', 'tims', 'px', 'countries', 'devices', 'ip', 'ip_address',
        'v_ip', 'created_at', 'updated_at', 'email_verified_at', 'expires_at', 'last_seen_at',
        'ended_at', 'revoked_at', 'date', 'time', 'last_activity',
    ];

    private const TEXTUAL_TYPES = ['char', 'enum', 'json', 'longtext', 'mediumtext', 'string', 'text', 'varchar', 'tinytext'];

    private ?array $catalogCache = null;

    public function catalog(): array
    {
        if ($this->catalogCache !== null) {
            return $this->catalogCache;
        }

        $tables = [];
        $availableTables = [];
        $candidateColumnsTotal = 0;

        foreach (self::TABLES as $table => $definition) {
            $available = $this->safeHasTable($table);
            $candidateColumns = $available ? $this->candidateColumns($table) : [];

            $tables[$table] = [
                'key' => $table,
                'label' => $definition['label'],
                'pk' => $definition['pk'] ?? null,
                'available' => $available,
                'candidate_columns' => $candidateColumns,
                'candidate_column_count' => count($candidateColumns),
            ];

            if ($available) {
                $availableTables[] = $table;
                $candidateColumnsTotal += count($candidateColumns);
            }
        }

        $presets = [];
        foreach (self::PRESETS as $key => $definition) {
            $resolvedTables = array_values(array_intersect($definition['tables'], $availableTables));
            $presets[$key] = [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'icon' => $definition['icon'],
                'tables' => $resolvedTables,
                'table_count' => count($resolvedTables),
            ];
        }

        return $this->catalogCache = [
            'tables' => $tables,
            'presets' => $presets,
            'available_tables' => $availableTables,
            'candidate_columns_total' => $candidateColumnsTotal,
        ];
    }

    public function pageSummary(): array
    {
        $catalog = $this->catalog();

        return [
            'available_tables_count' => count($catalog['available_tables']),
            'candidate_columns_total' => $catalog['candidate_columns_total'],
            'preset_count' => count($catalog['presets']),
        ];
    }

    public function presetKeys(): array
    {
        return array_keys(self::PRESETS);
    }

    public function tableKeys(): array
    {
        return array_keys(self::TABLES);
    }

    public function normalizeSelection(?string $preset, array $tables): array
    {
        $catalog = $this->catalog();
        $availableTables = $catalog['available_tables'];
        $presetKey = array_key_exists((string) $preset, self::PRESETS) ? (string) $preset : 'all';
        $requestedTables = array_values(array_unique(array_intersect($tables, $availableTables)));
        $presetTables = $catalog['presets'][$presetKey]['tables'] ?? [];
        $resolvedTables = $requestedTables !== [] ? $requestedTables : $presetTables;

        if ($resolvedTables === []) {
            $resolvedTables = $availableTables;
        }

        return [
            'preset' => $presetKey,
            'preset_label' => self::PRESETS[$presetKey]['label'],
            'tables' => array_values($resolvedTables),
            'table_labels' => array_values(array_map(
                fn (string $table): string => self::TABLES[$table]['label'] ?? $table,
                $resolvedTables
            )),
        ];
    }

    public function selectionSignature(array $tables, bool $convertCharset): string
    {
        $normalizedTables = array_values(array_unique($tables));
        sort($normalizedTables);

        return hash(
            'sha256',
            json_encode([
                'tables' => $normalizedTables,
                'convert_charset' => $convertCharset,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    public function preview(array $selection, bool $convertCharset): array
    {
        return $this->scan($selection, $convertCharset, false);
    }

    public function run(array $selection, bool $convertCharset): array
    {
        return $this->scan($selection, $convertCharset, true);
    }

    private function scan(array $selection, bool $convertCharset, bool $applyChanges): array
    {
        $catalog = $this->catalog();
        $warnings = [];
        $errors = [];
        $groups = [];
        $capturedExamples = 0;
        $tablesScanned = 0;
        $rowsScanned = 0;
        $rowsDetected = 0;
        $fieldsDetected = 0;
        $updatedRows = 0;
        $updatedFields = 0;
        $selectedCandidateColumns = 0;
        $charsetTablesConverted = 0;
        $resultsTruncated = false;

        foreach ($selection['tables'] as $table) {
            $meta = $catalog['tables'][$table] ?? null;

            if (!$meta || !$meta['available']) {
                $warnings[] = sprintf('تم تجاوز الجدول `%s` لأنه غير موجود في هذا التثبيت.', $table);
                continue;
            }

            $candidateColumns = $meta['candidate_columns'];
            $selectedCandidateColumns += count($candidateColumns);

            if ($candidateColumns === []) {
                $warnings[] = sprintf('لا توجد أعمدة نصية مناسبة للفحص داخل الجدول `%s`.', $meta['label']);
                continue;
            }

            if ($convertCharset && $this->convertTableCharset($table, $errors, $warnings)) {
                $charsetTablesConverted++;
            }

            $tablesScanned++;

            foreach ($this->rowStream($table, $meta['pk']) as $row) {
                $rowsScanned++;
                $rowUpdates = [];
                $rowMatched = false;

                foreach ($candidateColumns as $column) {
                    $value = $row->{$column} ?? null;

                    if (!is_string($value) || trim($value) === '') {
                        continue;
                    }

                    $fixed = ArabicStringRepair::fix($value);
                    if ($fixed === null || $fixed === $value) {
                        continue;
                    }

                    $fieldsDetected++;
                    $rowMatched = true;
                    $rowUpdates[$column] = $fixed;

                    if ($capturedExamples < self::EXAMPLE_LIMIT) {
                        $groups[$table]['meta'] = [
                            'label' => $meta['label'],
                            'table' => $table,
                        ];
                        $groups[$table]['items'][] = [
                            'table' => $table,
                            'table_label' => $meta['label'],
                            'column' => $column,
                            'identifier' => $this->rowIdentifier($row, $table, $meta['pk']),
                            'before' => Str::limit($value, 220),
                            'after' => Str::limit($fixed, 220),
                        ];
                        $capturedExamples++;
                    } else {
                        $resultsTruncated = true;
                    }
                }

                if (!$rowMatched) {
                    continue;
                }

                $rowsDetected++;

                if (!$applyChanges) {
                    continue;
                }

                try {
                    $updated = $this->updateRow($table, $row, $meta['pk'], $rowUpdates);

                    if ($updated > 0) {
                        $updatedRows++;
                        $updatedFields += count($rowUpdates);
                    } else {
                        $errors[] = sprintf(
                            'تعذر تحديث السجل `%s` في الجدول `%s` رغم اكتشاف حقول قابلة للإصلاح.',
                            $this->rowIdentifier($row, $table, $meta['pk']),
                            $meta['label']
                        );
                    }
                } catch (Throwable $e) {
                    $errors[] = sprintf(
                        'فشل تحديث السجل `%s` في الجدول `%s`: %s',
                        $this->rowIdentifier($row, $table, $meta['pk']),
                        $meta['label'],
                        $e->getMessage()
                    );
                }
            }
        }

        if ($resultsTruncated) {
            $warnings[] = 'تم اختصار قائمة الأمثلة المعروضة للحفاظ على سرعة الصفحة. الأرقام الإجمالية ما زالت كاملة.';
        }

        foreach ($groups as $table => $group) {
            $groups[$table]['count'] = count($group['items'] ?? []);
        }

        return [
            'mode' => $applyChanges ? 'run' : 'preview',
            'selection' => [
                'preset' => $selection['preset'],
                'preset_label' => $selection['preset_label'],
                'tables' => $selection['tables'],
                'table_labels' => $selection['table_labels'],
                'convert_charset' => $convertCharset,
                'signature' => $this->selectionSignature($selection['tables'], $convertCharset),
            ],
            'summary' => [
                'tables_selected' => count($selection['tables']),
                'tables_scanned' => $tablesScanned,
                'rows_scanned' => $rowsScanned,
                'rows_detected' => $rowsDetected,
                'fields_detected' => $fieldsDetected,
                'updated_rows' => $updatedRows,
                'updated_fields' => $updatedFields,
                'candidate_columns' => $selectedCandidateColumns,
                'captured_examples' => $capturedExamples,
                'charset_tables_converted' => $charsetTablesConverted,
                'scanned_at' => now()->format('Y-m-d H:i'),
            ],
            'groups' => $groups,
            'warnings' => array_values(array_unique($warnings)),
            'errors' => array_values(array_unique($errors)),
        ];
    }

    private function rowStream(string $table, ?string $primaryKey): iterable
    {
        $query = DB::table($table);

        if (is_string($primaryKey) && $primaryKey !== '' && $this->safeHasColumn($table, $primaryKey)) {
            $query->orderBy($primaryKey);
        }

        return $query->cursor();
    }

    private function updateRow(string $table, object $row, ?string $primaryKey, array $updates): int
    {
        if ($updates === []) {
            return 0;
        }

        if (is_string($primaryKey) && $primaryKey !== '' && $this->safeHasColumn($table, $primaryKey)) {
            $rowValue = $row->{$primaryKey} ?? null;

            if ($rowValue !== null) {
                return DB::table($table)->where($primaryKey, $rowValue)->update($updates);
            }
        }

        if ($table === 'setting') {
            return DB::table($table)->limit(1)->update($updates);
        }

        throw new RuntimeException('تعذر تحديد المفتاح الأساسي للسجل المطلوب تحديثه.');
    }

    private function rowIdentifier(object $row, string $table, ?string $primaryKey): string
    {
        if (is_string($primaryKey) && $primaryKey !== '' && isset($row->{$primaryKey})) {
            return (string) $row->{$primaryKey};
        }

        return $table === 'setting' ? 'setting' : 'غير متاح';
    }

    private function candidateColumns(string $table): array
    {
        $columns = $this->safeColumnListing($table);

        return array_values(array_filter($columns, function (string $column) use ($table): bool {
            $normalized = Str::lower($column);

            if (in_array($normalized, self::EXCLUDED_COLUMNS, true)) {
                return false;
            }

            if (
                Str::endsWith($normalized, ['_id', '_at', '_url', '_path', '_file', '_image', '_img', '_icon', '_token', '_hash', '_ip'])
                || Str::startsWith($normalized, ['img_', 'icon_', 'file_', 'token_', 'hash_', 'ip_'])
            ) {
                return false;
            }

            return $this->isTextualColumn($table, $column);
        }));
    }

    private function isTextualColumn(string $table, string $column): bool
    {
        try {
            $type = Schema::getColumnType($table, $column);
        } catch (Throwable) {
            return true;
        }

        return in_array(Str::lower((string) $type), self::TEXTUAL_TYPES, true);
    }

    private function safeHasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    private function safeHasColumn(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (Throwable) {
            return false;
        }
    }

    private function safeColumnListing(string $table): array
    {
        try {
            return Schema::getColumnListing($table);
        } catch (Throwable) {
            return [];
        }
    }

    private function convertTableCharset(string $table, array &$errors, array &$warnings): bool
    {
        $driver = DB::getDriverName();

        if ($driver !== 'mysql') {
            $warnings[] = 'خيار تحويل الترميز متاح فقط على قواعد بيانات MySQL أو MariaDB. تم تجاهله في البيئة الحالية.';
            return false;
        }

        try {
            DB::statement(sprintf(
                'ALTER TABLE `%s` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                str_replace('`', '``', $table)
            ));

            return true;
        } catch (Throwable $e) {
            $errors[] = sprintf('تعذر تحويل ترميز الجدول `%s`: %s', $table, $e->getMessage());
            return false;
        }
    }
}
