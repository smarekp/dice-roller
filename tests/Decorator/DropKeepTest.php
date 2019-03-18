<?php

/**
 * PHP Dice Roller (https://github.com/bakame-php/dice-roller/)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bakame\DiceRoller\Test\Decorator;

use Bakame\DiceRoller\ClassicDie;
use Bakame\DiceRoller\Cup;
use Bakame\DiceRoller\CustomDie;
use Bakame\DiceRoller\Decorator\DropKeep;
use Bakame\DiceRoller\Exception\CanNotBeRolled;
use Bakame\DiceRoller\Profiler\Logger;
use Bakame\DiceRoller\Profiler\LogProfiler;
use Bakame\DiceRoller\Rollable;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

/**
 * @coversDefaultClass Bakame\DiceRoller\Decorator\DropKeep
 */
final class DropKeepTest extends TestCase
{
    /**
     * @var \Bakame\DiceRoller\Cup
     */
    private $cup;

    public function setUp(): void
    {
        $this->cup = Cup::createFromRollable(4, new ClassicDie(6));
    }

    /**
     * @covers ::__construct
     */
    public function testConstructorThrows1(): void
    {
        self::expectException(CanNotBeRolled::class);
        new DropKeep($this->cup, DropKeep::DROP_LOWEST, 6);
    }

    /**
     * @covers ::__construct
     */
    public function testConstructorThrows2(): void
    {
        self::expectException(CanNotBeRolled::class);
        new DropKeep($this->cup, 'foobar', 3);
    }

    /**
     * @covers ::toString
     */
    public function testToString(): void
    {
        $cup = new DropKeep((new Cup())->withAddedRollable(
            new ClassicDie(3),
            new CustomDie(-3, -2, -1),
            new ClassicDie(4)
        ), DropKeep::DROP_LOWEST, 2);

        self::assertSame('(D3+D[-3,-2,-1]+D4)DL2', $cup->toString());
    }


    /**
     * @covers ::roll
     */
    public function testGetTrace(): void
    {
        $dice1 = new class() implements Rollable {
            public function getMinimum(): int
            {
                return 1;
            }

            public function getMaximum(): int
            {
                return 1;
            }

            public function roll(): int
            {
                return 1;
            }

            public function toString(): string
            {
                return '1';
            }
        };

        $dice2 = new class() implements Rollable {
            public function getMinimum(): int
            {
                return 2;
            }

            public function getMaximum(): int
            {
                return 2;
            }

            public function roll(): int
            {
                return 2;
            }

            public function toString(): string
            {
                return '2';
            }
        };

        $rollables = (new Cup())->withAddedRollable($dice1, clone $dice1, $dice2, clone $dice2);
        $cup = new DropKeep($rollables, DropKeep::DROP_LOWEST, 1);
        self::assertSame(5, $cup->roll());
    }

    /**
     * @covers ::__construct
     * @covers ::getMinimum
     * @covers ::getMaximum
     * @covers ::calculate
     * @covers ::keepLowest
     * @covers ::keepHighest
     * @covers ::drop
     * @covers ::dropLowest
     * @covers ::dropHighest
     * @covers ::roll
     * @dataProvider validParametersProvider
     */
    public function testModifier(string $algo, int $threshold, int $min, int $max): void
    {
        $cup = new DropKeep($this->cup, $algo, $threshold);
        $res = $cup->roll();
        self::assertSame($min, $cup->getMinimum());
        self::assertSame($max, $cup->getMaximum());
        self::assertGreaterThanOrEqual($min, $res);
        self::assertLessThanOrEqual($max, $res);
    }

    public function validParametersProvider(): iterable
    {
        return [
            'dl' => [
                'algo' => DropKeep::DROP_LOWEST,
                'threshold' => 3,
                'min' => 1,
                'max' => 6,
            ],
            'dh' => [
                'algo' => DropKeep::DROP_HIGHEST,
                'threshold' => 2,
                'min' => 2,
                'max' => 12,
            ],
            'kl' => [
                'algo' => DropKeep::KEEP_LOWEST,
                'threshold' => 2,
                'min' => 2,
                'max' => 12,
            ],
            'kh' => [
                'algo' => DropKeep::KEEP_HIGHEST,
                'threshold' => 3,
                'min' => 3,
                'max' => 18,
            ],
        ];
    }

    /**
     * @covers ::__construct
     * @covers ::getMinimum
     * @covers ::getMaximum
     * @covers ::roll
     * @covers ::calculate
     * @covers ::setTrace
     * @covers ::getTrace
     * @covers \Bakame\DiceRoller\Profiler\LogProfiler
     * @covers \Bakame\DiceRoller\Profiler\Logger
     * @covers ::getInnerRollable
     */
    public function testProfiler(): void
    {
        $logger = new Logger();
        $tracer = new LogProfiler($logger, LogLevel::DEBUG);
        $pool = new Cup(
            new ClassicDie(3),
            new ClassicDie(3),
            new ClassicDie(4)
        );
        $roll = new DropKeep($pool, DropKeep::DROP_LOWEST, 2);
        $roll->setProfiler($tracer);
        self::assertEmpty($roll->getTrace());
        $roll->roll();
        self::assertNotEmpty($roll->getTrace());
        $roll->getMaximum();
        $roll->getMinimum();
        self::assertSame($pool, $roll->getInnerRollable());
        self::assertCount(3, $logger->getLogs(LogLevel::DEBUG));
    }
}
