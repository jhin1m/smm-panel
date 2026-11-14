{{-- Fixture cố ý lỗi: kiểm /up không phụ thuộc view trong custom/. --}}
@php throw new RuntimeException("custom view broken"); @endphp
