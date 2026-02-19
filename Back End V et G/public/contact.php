<?php include 'header.php'; ?>

<section class="container my-5">
    <h1 class="text-center mb-4">Contactez-nous</h1>

    <?php if(isset($_GET['success'])): ?>
        <div class="alert alert-success">
            Votre message a bien été envoyé.
        </div>
    <?php endif; ?>

    <form method="POST" action="contact_send.php">
        
        <div class="mb-3">
            <label for="title" class="form-label">Titre *</label>
            <input type="text" class="form-control" id="title" name="title" required>
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Votre email *</label>
            <input type="email" class="form-control" id="email" name="email" required>
        </div>

        <div class="mb-3">
            <label for="message" class="form-label">Description *</label>
            <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
        </div>

        <button type="submit" class="btn btn-primary w-100">
            Envoyer ma demande
        </button>

    </form>
</section>

<?php include 'footer.php'; ?>
