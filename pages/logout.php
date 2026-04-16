<?php
logout();
set_flash('success', 'Wylogowano poprawnie.');
redirect('index.php?page=home');
