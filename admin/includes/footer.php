 <!-- Footer -->
    <footer class="admin-footer">
        <div class="footer-content">
            <div class="footer-left">
                <p>&copy; <?php echo date('Y'); ?> <strong>WittyMart</strong> - Admin Panel</p>
            </div>
            <div class="footer-center">
                <p>
                    <i class="fas fa-code"></i> Built with <i class="fas fa-heart" style="color: #ff6b6b;"></i> by 
                    <a href="https://github.com/yourusername" target="_blank">Witty Highbrow Technologies</a>
                </p>
            </div>
            <div class="footer-right">
                <p>
                    <i class="fas fa-clock"></i> 
                    <span id="server-time"><?php echo date('H:i:s'); ?></span>
                    <span class="footer-divider">|</span>
                    <span id="server-date"><?php echo date('d M Y'); ?></span>
                </p>
            </div>
        </div>
    </footer>

    <!-- Dynamic Clock Script -->
    <script>
        function updateClock() {
            const now = new Date();
            const time = now.toLocaleTimeString('en-US', { hour12: false });
            const date = now.toLocaleDateString('en-US', { 
                day: '2-digit', 
                month: 'short', 
                year: 'numeric' 
            });
            
            document.getElementById('server-time').textContent = time;
            document.getElementById('server-date').textContent = date;
        }
        
        // Update clock every second
        setInterval(updateClock, 1000);
        
        // Initial call to set clock immediately
        updateClock();
    </script>
</body>
</html>
