</main>
</div>
<script>
(function () {
    const button = document.getElementById('menuButton');
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');

    function toggle() {
        sidebar?.classList.toggle('-translate-x-full');
        backdrop?.classList.toggle('hidden');
    }

    button?.addEventListener('click', toggle);
    backdrop?.addEventListener('click', toggle);
})();
</script>
</body>
</html>
