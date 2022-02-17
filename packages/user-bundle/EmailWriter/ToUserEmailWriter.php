<?php

namespace Draw\Bundle\UserBundle\EmailWriter;

use Doctrine\ORM\EntityRepository;
use Draw\Bundle\PostOfficeBundle\EmailWriter\EmailWriterInterface;
use Draw\Bundle\UserBundle\Email\ToUserEmailInterface;
use Symfony\Component\Mime\Email;

class ToUserEmailWriter implements EmailWriterInterface
{
    private $userEntityRepository;

    public static function getForEmails(): array
    {
        return ['compose' => -255];
    }

    public function __construct(EntityRepository $drawUserEntityRepository)
    {
        $this->userEntityRepository = $drawUserEntityRepository;
    }

    public function compose(ToUserEmailInterface $email)
    {
        if (!$email instanceof Email || $email->getTo()) {
            return;
        }

        $user = $this->userEntityRepository->find($email->getUserIdentifier());

        if (!$user) {
            return;
        }

        $email->to($user->getEmail());
    }
}
