<?php

namespace App\Controllers;

use App\Models\Expense;
use App\Support\Csrf;

class ExpenseController
{
    public static function index(): void
    {
        $expenses = Expense::all();
        require __DIR__ . '/../Views/admin/expenses.php';
    }

    public static function create(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        $amount = (float) $_POST['amount'];
        $category = trim((string) $_POST['category']);
        if ($category === '' || $amount <= 0) {
            $_SESSION['flash_error'] = 'Category is required and amount must be greater than zero.';
            header('Location: /admin/expenses');
            exit;
        }
        if (mb_strlen($category) > 100) {
            $_SESSION['flash_error'] = 'Category must be 100 characters or fewer.';
            header('Location: /admin/expenses');
            exit;
        }
        Expense::create(
            (int) $_SESSION['user_id'],
            $category,
            $amount,
            $_POST['description'] ?: null,
            null
        );
        header('Location: /admin/expenses');
        exit;
    }
}
