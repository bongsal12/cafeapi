<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Determine if user can view any orders.
     */
    public function viewAny(User $user): bool
    {
        // Admin can view all orders, staff can view orders
        return $user->isAdmin() || $user->isStaff();
    }

    /**
     * Determine if user can view a specific order.
     */
    public function view(User $user, Order $order): bool
    {
        // Admin can view any order, staff can view any order
        return $user->isAdmin() || $user->isStaff();
    }

    /**
     * Determine if user can create an order.
     */
    public function create(User $user): bool
    {
        // Both admin and staff can create orders
        return $user->isAdmin() || $user->isStaff();
    }

    /**
     * Determine if user can update order status.
     */
    public function updateStatus(User $user, Order $order): bool
    {
        // Both admin and staff can update order status
        return $user->isAdmin() || $user->isStaff();
    }

    /**
     * Determine if user can cancel an order.
     */
    public function cancel(User $user, Order $order): bool
    {
        // Both admin and staff can cancel orders
        return $user->isAdmin() || $user->isStaff();
    }
}
