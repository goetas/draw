<?php

namespace Draw\Bundle\UserBundle\Tests\AccountLocker\Entity;

use Draw\Bundle\UserBundle\Entity\LockableUserInterface;
use Draw\Bundle\UserBundle\Entity\LockableUserTrait;
use Draw\Bundle\UserBundle\Entity\SecurityUserInterface;
use Draw\Bundle\UserBundle\Entity\SecurityUserTrait;
use Draw\Bundle\UserBundle\Entity\UserLock;
use Draw\Component\Messenger\DoctrineMessageBusHook\Entity\MessageHolderTrait;
use PHPUnit\Framework\TestCase;

class LockableUserTraitTest extends TestCase
{
    private LockableUserInterface $object;

    protected function setUp(): void
    {
        $this->object = new class() implements SecurityUserInterface, LockableUserInterface {
            use LockableUserTrait;
            use MessageHolderTrait;
            use SecurityUserTrait;

            public function getId()
            {
                // TODO: Implement getId() method.
            }
        };
    }

    public function testManualLockMutator(): void
    {
        static::assertFalse(
            $this->object->hasManualLock()
        );

        static::assertNull(
            $this->object->getLocks()[UserLock::REASON_MANUAL_LOCK] ?? null
        );

        static::assertSame(
            $this->object,
            $this->object->setManualLock(true)
        );

        static::assertTrue(
            $this->object->hasManualLock()
        );

        static::assertNotNull(
            $this->object->getLocks()[UserLock::REASON_MANUAL_LOCK] ?? null
        );

        static::assertSame(
            $this->object,
            $this->object->setManualLock(false)
        );

        static::assertNull(
            $this->object->getLocks()[UserLock::REASON_MANUAL_LOCK] ?? null
        );
    }

    public function testLock(): void
    {
        $lock = new UserLock(uniqid('reason-'));

        static::assertSame(
            $lock,
            $this->object->lock($lock)
        );

        static::assertTrue($this->object->getUserLocks()->contains($lock));

        static::assertSame(
            $lock,
            $this->object->lock(clone $lock),
            'Must return the current lock since they are the same'
        );

        $lock->setUnlockUntil(new \DateTimeImmutable());

        $newLock = (clone $lock)->setLockOn(new \DateTimeImmutable());
        static::assertSame(
            $newLock,
            $this->object->lock($newLock),
            'Must return the new lock since they are different'
        );

        static::assertTrue($this->object->getUserLocks()->contains($newLock));

        static::assertFalse(
            $this->object->getUserLocks()->contains($lock),
            'Old lock must be remove since the new lock has the same reason'
        );
    }

    public function testUnlock(): void
    {
        static::assertNull(
            $this->object->unlock(uniqid('reason-')),
            'If not lock is found it just return null'
        );

        $lock = new UserLock(uniqid('reason-'));

        $this->object->lock($lock);

        static::assertSame(
            $lock,
            $this->object->unlock($lock->getReason(), $until = new \DateTimeImmutable())
        );

        static::assertTrue(
            $this->object->getUserLocks()->contains($lock),
            'Lock must be kept since we unlock it only until a specific date'
        );

        static::assertSame(
            $until->getTimestamp(),
            $lock->getUnlockUntil()->getTimestamp(),
        );

        static::assertSame(
            $lock,
            $this->object->unlock($lock->getReason())
        );

        static::assertFalse(
            $this->object->getUserLocks()->contains($lock),
            'Lock must be remove from user locks since we remove it completely'
        );

        static::assertNull(
            $this->object->unlock($lock->getReason())
        );
    }

    public function testGeLocks(): void
    {
        static::assertEmpty($this->object->getLocks());

        $this->object->setManualLock(true);

        $locks = $this->object->getLocks();

        static::assertCount(1, $locks);

        /** @var UserLock $lock */
        $lock = current($locks);

        static::assertArrayHasKey($lock->getReason(), $locks);
    }

    public function testIsLocked(): void
    {
        static::assertFalse($this->object->isLocked());

        $this->object->setManualLock(true);

        static::assertTrue($this->object->isLocked());

        /** @var UserLock $lock */
        $lock = current($this->object->getLocks());

        $lock->setUnlockUntil(new \DateTimeImmutable('+ 1 days'));

        static::assertFalse($this->object->isLocked());
    }

    public function testUserLockMutator(): void
    {
        static::assertCount(0, $this->object->getUserLocks());

        static::assertSame(
            $this->object,
            $this->object->addUserLock($value = new UserLock(uniqid('reason-')))
        );

        static::assertSame(
            $value->getId(),
            $this->object->getOnHoldMessages(false)[0]->getUserLockId()
        );

        static::assertCount(1, $this->object->getUserLocks());
        static::assertSame(
            $value,
            $this->object->getUserLocks()[0]
        );

        static::assertSame(
            $this->object,
            $value->getUser()
        );

        static::assertSame(
            $this->object,
            $this->object->removeUserLock($value)
        );

        static::assertCount(0, $this->object->getUserLocks());
    }
}
