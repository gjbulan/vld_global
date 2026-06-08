
</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const body = document.body;
    const menuBtn = document.getElementById("memberMenuBtn");
    const overlay = document.getElementById("memberSidebarOverlay");

    if(menuBtn){
        menuBtn.addEventListener("click", function(){
            body.classList.toggle("member-sidebar-open");
        });
    }

    if(overlay){
        overlay.addEventListener("click", function(){
            body.classList.remove("member-sidebar-open");
        });
    }

    const navLinks = document.querySelectorAll(".sidebar-nav a");

    navLinks.forEach(function(link){
        link.addEventListener("click", function(){
            body.classList.remove("member-sidebar-open");
        });
    });

});
</script>

</body>
</html>