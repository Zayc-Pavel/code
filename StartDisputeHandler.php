<?php

namespace FutureWorld\Handler\Chat;

use FutureWorld\Domain\Chat\ConversationStarter;
use FutureWorld\Domain\Order\OrderRepository;
use FutureWorld\Domain\User\User;
use FutureWorld\Domain\User\UserRepository;

/**
 * Class StartDisputeHandler
 * @package FutureWorld\Handler\Chat
 */
class StartDisputeHandler
{
    private $orders;
    private $users;
    private $conversations;

    public function __construct(OrderRepository $orders, UserRepository $users, ConversationStarter $conversations)
    {
        $this->orders = $orders;
        $this->users = $users;
        $this->conversations = $conversations;
    }

    /**
     * @param StartDisputeRequest $request
     * @param User $currentUser
     * @return mixed
     */
    public function __invoke(StartDisputeRequest $request, User $currentUser)
    {
        $order = $this->orders->get($request->get('order_id'));

        if ($currentUser->isConsumer()) {

            return $this->conversations->createDispute($currentUser, $order, $this->users->getAdmin());
        }

        if (!$request->has('user_id')) {

            return $this->conversations->createDispute($currentUser, $order, $order->getBuyer(), $order->seller());
        }

        return $this->conversations->createDispute(
            $currentUser,
            $order,
            $this->users->get($request->has('user_id'))
        );
    }
}
