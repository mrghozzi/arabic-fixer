@extends('theme::layouts.admin')

@section('content')
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">إصلاح اللغة العربية</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">الرئيسية</a></li>
            <li class="breadcrumb-item">الإضافات</li>
            <li class="breadcrumb-item">إصلاح اللغة العربية</li>
        </ul>
    </div>
</div>

<div class="main-content">
    <div class="row">
        <div class="col-12">
            
            @if(session('success'))
            <div class="alert alert-success mt-3 mb-3">
                {{ session('success') }}
            </div>
            @endif

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">إصلاح مشكلة الرموز الغريبة (الترميز)</h5>
                </div>
                <div class="card-body">
                    <p>
                        هذه الإضافة تقوم بفحص جميع النصوص في قاعدة البيانات والبحث عن النصوص العربية التي تظهر بشكل رموز غريبة (مثل <code>Ø§Ù„Ø¹Ø±Ø¨ÙŠØ©</code>) بسبب الترقية من الإصدار القديم (قاعدة بيانات بترميز latin1).
                    </p>
                    <p class="text-danger">
                        <strong>تنبيه:</strong> يرجى أخذ نسخة احتياطية (Backup) لقاعدة البيانات قبل إجراء هذه العملية تحسباً لأي طارئ.
                    </p>

                    <form action="{{ route('admin.arabic-fixer.run') }}" method="POST"  onsubmit="return confirm('هل أنت متأكد من رغبتك في تشغيل أداة الإصلاح الآن؟ تأكد من أخذ نسخة احتياطية أولاً.')">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-lg mt-3">
                            <i class="feather-tool me-2"></i>
                            بدء عملية إصلاح اللغة العربية
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
