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
        
        $excludeColumns = [
            'email', 'password', 'remember_token', 'avatar', 'cover', 
            'url', 'slug', 'token', 'ip', 'ip_address', 'file', 'image', 'icon',
            'created_at', 'updated_at', 'email_verified_at', 'date', 'link'
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
                        // Check for Mojibake characters common in double-encoded Arabic (Latin1 reading UTF-8)
                        if (preg_match('/[ØÙ]/', $val)) {
                            // Convert back from Latin-1 reading to actual UTF-8
                            $fixed = @mb_convert_encoding($val, 'ISO-8859-1', 'UTF-8');
                            
                            // Ensure the result is valid
                            if ($fixed && mb_check_encoding($fixed, 'UTF-8')) {
                                $updates[$col] = $fixed;
                                $fixedFields++;
                            }
                        }
                    }
                }
                
                if (!empty($updates)) {
                    if (isset($row->id)) {
                        DB::table($table)->where('id', $row->id)->update($updates);
                        $fixedCount++;
                    } else if (isset($row->name) && $table === 'options') {
                        DB::table($table)->where('name', $row->name)->update($updates);
                        $fixedCount++;
                    } else if ($table === 'setting') {
                        DB::table($table)->update($updates);
                        $fixedCount++;
                    }
                }
            }
        }
        
        return back()->with('success', "تم إصلاح $fixedFields حقل عبر $fixedCount سجل في قاعدة البيانات بنجاح.");
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
