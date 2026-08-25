<?php

namespace App\Observers;

use App\Models\Order;
use App\Notifications\AdminNotification;
use App\Services\AdminNotificationDispatcher;

class OrderObserver
{
    public function __construct(
        private readonly AdminNotificationDispatcher $dispatcher
    ) {
    }

    public function created(
        Order $order
    ): void {
        /*
         * Some imports/tests may create an order with its
         * public order number already populated.
         */
        if (
            $order->order_number
            !== null
        ) {
            $this->notifyAdmins(
                $order
            );
        }
    }

    public function updated(
        Order $order
    ): void {
        if (
            ! $order->wasChanged(
                'order_number'
            )
            || $order->order_number
                === null
        ) {
            return;
        }

        $previous =
            $order->getPrevious();

        if (
            ($previous['order_number'] ?? null)
            !== null
        ) {
            return;
        }

        /*
         * The real checkout flow inserts the order first,
         * then assigns ORD-xxxxxx after obtaining the
         * database ID.
         *
         * Notify only on that first assignment.
         */
        $this->notifyAdmins(
            $order
        );
    }

    private function notifyAdmins(
        Order $order
    ): void {
        $this->dispatcher->send(
            new AdminNotification(
                type: 'order',
                title: 'New order',
                message:
                    "Order {$order->order_number} requires attention.",
                actionUrl:
                    "/api/v1/admin/orders/{$order->id}",
                entityType: 'order',
                entityId: $order->id
            )
        );
    }
}