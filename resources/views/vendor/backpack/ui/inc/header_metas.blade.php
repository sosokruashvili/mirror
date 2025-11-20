{{-- PWA Meta Tags --}}
<meta name="application-name" content="Mirror Gallery ERP">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Mirror ERP">
<meta name="mobile-web-app-capable" content="yes">
<meta name="msapplication-TileColor" content="#206bc4">
<meta name="msapplication-config" content="/browserconfig.xml">

{{-- PWA Manifest --}}
<link rel="manifest" href="{{ asset('manifest.json') }}">

{{-- Apple Touch Icons --}}
<link rel="apple-touch-icon" sizes="72x72" href="{{ asset('icons/icon-72x72.png') }}">
<link rel="apple-touch-icon" sizes="96x96" href="{{ asset('icons/icon-96x96.png') }}">
<link rel="apple-touch-icon" sizes="128x128" href="{{ asset('icons/icon-128x128.png') }}">
<link rel="apple-touch-icon" sizes="144x144" href="{{ asset('icons/icon-144x144.png') }}">
<link rel="apple-touch-icon" sizes="152x152" href="{{ asset('icons/icon-152x152.png') }}">
<link rel="apple-touch-icon" sizes="192x192" href="{{ asset('icons/icon-192x192.png') }}">
<link rel="apple-touch-icon" sizes="384x384" href="{{ asset('icons/icon-384x384.png') }}">
<link rel="apple-touch-icon" sizes="512x512" href="{{ asset('icons/icon-512x512.png') }}">

{{-- Theme Color --}}
<meta name="theme-color" content="#206bc4">

{{-- Service Worker Registration Script --}}
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then((registration) => {
                console.log('SW registered: ', registration);
            })
            .catch((registrationError) => {
                console.log('SW registration failed: ', registrationError);
            });
    });
}
</script>

