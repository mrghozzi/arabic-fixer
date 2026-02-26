<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use App\Helpers\Hooks;

Route::middleware(['web', 'auth', 'admin'])->group(function () {
    Route::get('/admin/arabic-fixer', function () {
        return view('arabic_fixer::index');
    })->name('admin.arabic-fixer.index');

    Route::post('/admin/arabic-fixer/run', function (Request $request) {
        $tables = [
            'setting', 'options', 'ads', 'news', 'forum', 'f_coment',
            'cat_dir', 'f_cat', 'directory', 'users', 'messages', 'report',
            'banner', 'link', 'notif', 'visits'
        ];

        $errors = [];
        
        // 1. Convert Database to utf8mb4
        try {
            $dbName = DB::getDatabaseName();
            DB::statement("ALTER DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\Exception $e) {
            $errors[] = "DB Alter Error: " . $e->getMessage();
        }
        
        $excludeColumns = [
            'password', 'email', 'remember_token', 'avatar', 'cover', 
            'url', 'slug', 'token', 'ip', 'ip_address', 'file', 'image', 'icon',
            'created_at', 'updated_at', 'email_verified_at', 'date', 'link',
            'o_type'
        ];
        
        $fixedCount = 0;
        $fixedFields = 0;
        
        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) continue;
            
            $allColumns = Schema::getColumnListing($table);
            $rows = DB::table($table)->get();
            
            foreach ($rows as $row) {
                $updates = [];
                foreach ($allColumns as $col) {
                    if (in_array(strtolower($col), $excludeColumns)) continue;
                    
                        $val = $row->$col;
                        if (is_string($val) && !empty($val)) {
                            // Convert column to utf8mb4 just in time if we find Arabic
                            if (preg_match('/[ØÙ]/', $val)) {
                                try {
                                    DB::statement("ALTER TABLE `$table` MODIFY `$col` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                                } catch (\Exception $ex) {
                                    try {
                                        DB::statement("ALTER TABLE `$table` MODIFY `$col` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                                    } catch (\Exception $ex2) {
                                        $errors[] = "Col Alter ($table.$col): " . $ex2->getMessage();
                                    }
                                }
                                
                                $fixed = @mb_convert_encoding($val, 'ISO-8859-1', 'UTF-8');
                                if ($fixed && mb_check_encoding($fixed, 'UTF-8')) {
                                    $updates[$col] = $fixed;
                                    $fixedFields++;
                                }
                            }
                        }
                    }
                    
                    if (!empty($updates)) {
                    if (isset($row->id)) {
                        try {
                            DB::table($table)->where('id', $row->id)->update($updates);
                            $fixedCount++;
                        } catch (\Exception $ex) {
                            $errors[] = "Update Error ($table ID {$row->id}): " . $ex->getMessage();
                        }
                    } else if (isset($row->name) && $table === 'options') {
                        try {
                            DB::table($table)->where('name', $row->name)->update($updates);
                            $fixedCount++;
                        } catch (\Exception $ex) {
                            $errors[] = "Update Error (options {$row->name}): " . $ex->getMessage();
                        }
                    } else if ($table === 'setting') {
                        try {
                            DB::table($table)->update($updates);
                            $fixedCount++;
                        } catch (\Exception $ex) {
                            $errors[] = "Update Error ($table): " . $ex->getMessage();
                        }
                    }
                }
            }
        }
        
        $msg = "تم إصلاح $fixedFields حقل عبر $fixedCount سجل في قاعدة البيانات بنجاح.";
        if (count($errors) > 0) {
            $msg .= "\nيوجد أخطاء: " . implode(" | ", array_slice($errors, 0, 3));
        }
        
        return back()->with('success', $msg);
    })->name('admin.arabic-fixer.run');
});

// Register views
View::addNamespace('arabic_fixer', __DIR__ . '/views');

// Add to Admin Menu
Hooks::add_action('admin_sidebar_menu', function() {
    $url = route('admin.arabic-fixer.index');
    echo '<li class="nxl-item">
            <a href="' . $url . '" class="nxl-link">
                <span class="nxl-micon"><i class="feather-tool"></i></span>
                <span class="nxl-mtext">إصلاح العربية (Mojibake)</span>
            </a>
          </li>';
});
