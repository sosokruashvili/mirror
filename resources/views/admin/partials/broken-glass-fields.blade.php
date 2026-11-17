{{--
    Break-detail fields for the team's "გატყდა" dialogs (team board and the order
    preview). Driven by public/assets/js/broken-glass-form.js; validated again on the
    server by App\Support\BrokenGlass\BreakDetails, which carries the same messages.

    @param string $idPrefix  unique prefix for the input ids on the page
--}}
@php
    $bgMachines = \App\Models\Machine::query()->active()->ordered()->get();
    $bgStages = \App\Models\BrokenGlass::detectionStages();
@endphp
<div class="broken-glass-fields" data-broken-glass-fields>
    <div class="alert alert-light border py-2 px-3 mb-3">
        დაზიანებული ფურცელი: <strong><span data-bg-sheet-area>—</span> მ²</strong>
    </div>

    <div class="mb-3">
        <label for="{{ $idPrefix }}UsableArea" class="form-label">გამოსადეგი მ² <span class="text-danger">*</span></label>
        <input type="text" inputmode="decimal" autocomplete="off" id="{{ $idPrefix }}UsableArea" class="form-control" data-bg-usable placeholder="მაგ. 0.35">
        <div class="form-text">გატეხილი ფურცლიდან რამდენი მ² გამოდგება. თუ არაფერი, ჩაწერე 0.</div>
    </div>

    <div class="mb-3">
        <label for="{{ $idPrefix }}Machine" class="form-label">დანადგარი, სადაც დაზიანდა <span class="text-danger">*</span></label>
        <select id="{{ $idPrefix }}Machine" class="form-select" data-bg-machine>
            <option value="">— აირჩიე —</option>
            @foreach($bgMachines as $machine)
                <option value="{{ $machine->id }}" data-requires-comment="{{ $machine->requires_comment ? '1' : '0' }}">{{ $machine->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="mb-3">
        <label for="{{ $idPrefix }}Stage" class="form-label">ეტაპი, სადაც დაზიანება აღმოაჩინეს <span class="text-danger">*</span></label>
        <select id="{{ $idPrefix }}Stage" class="form-select" data-bg-stage>
            <option value="">— აირჩიე —</option>
            @foreach($bgStages as $stage)
                <option value="{{ $stage->id }}">{{ $stage->title }}</option>
            @endforeach
        </select>
    </div>

    <div class="alert alert-danger py-2 px-3 mb-3 d-none" role="alert" data-bg-error></div>
</div>
