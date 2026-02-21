    </div><!-- end page-body -->
</div><!-- end main-content -->
<script>
// Auto-close alerts
document.querySelectorAll('.alert').forEach(a => {
    setTimeout(() => { a.style.opacity='0'; setTimeout(()=>a.remove(),400); }, 4000);
});
</script>
</body>
</html>
