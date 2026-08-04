<?php
require_once 'includes/config.php';


?>
    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - WittyMart</title>
     <link rel="icon" type="image/png" href="images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
 
</head>
<body>
 <?php include "header.php"; ?>
    <?php include "sidebar.php"; ?>


    <!-- Main Content -->
    <main>
        <section class="contact-section">
            <h1> Contact <span>Us</span></h1>
            <p>We'd love to hear from you! Whether you have a question, feedback, or just want to say hi – reach out to us using the form below.</p>
            
            <!-- Contact Info -->
            <div class="contact-info">
                <article>
                    <i class="fas fa-envelope"></i>
                    <h2>Email</h2>
                    <p><a href="mailto:kaujacob4@gmail.com">support@wittymart.co.ke</a></p>
                </article>
                <article>
                    <i class="fas fa-phone"></i>
                    <h2>Phone</h2>
                    <p>+254 768 374 497</p>
                </article>
                <article>
                    <i class="fas fa-map-marker-alt"></i>
                    <h2>Location</h2>
                    <p>Nairobi, Kenya</p>
                </article>
            </div>

            <!-- Contact Form -->
            <form class="contact-form" onsubmit="return handleContactForm(event)">
                <p id="form-status" class="form-status"></p>
                <label for="name">Your Name:</label>
                <input type="text" id="name" name="name" required placeholder="Steve Ochieng'">

                <label for="email">Your Email:</label>
                <input type="email" id="email" name="email" required placeholder="steveOchieng@example.com">

                <label for="message">Your Message:</label>
                <textarea id="message" name="message" rows="5" required placeholder="Write your message here..."></textarea>

                <button type="submit"><i class="fas fa-paper-plane"></i> Send Message</button>
            </form>

            <!-- Map -->
            <div class="map-placeholder">
                <h2> Find Us <span>Here</span></h2>
                <div class="map-box">
                    <iframe
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2042026.9634323379!2d37.01177459031261!3d-1.5629716108985743!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x1824b19cc6a8df91%3A0x629cdb0fc90d2def!2sKitui%20County!5e0!3m2!1sen!2ske!4v1746260272741!5m2!1sen!2ske"
                        allowfullscreen="" 
                        loading="lazy" 
                        referrerpolicy="no-referrer-when-downgrade"
                    ></iframe>
                </div>
            </div>
        </section>
    </main>

 <?php include "footer.php"; ?>

<script src="script.js"></script>
</body>
</html>
