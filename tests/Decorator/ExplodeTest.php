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
use Bakame\DiceRoller\Decorator\Explode;
use Bakame\DiceRoller\Exception\CanNotBeRolled;
use Bakame\DiceRoller\Factory;
use Bakame\DiceRoller\Pool;
use Bakame\DiceRoller\Profiler\Logger;
use Bakame\DiceRoller\Profiler\LogProfiler;
use Bakame\DiceRoller\Rollable;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

/**
 * @coversDefaultClass Bakame\DiceRoller\Decorator\Explode
 */
final class ExplodeTest extends TestCase
{
    /**
     * @var Cup
     */
    private $cup;

    public function setUp(): void
    {
        $this->cup = Cup::createFromRollable(4, new ClassicDie(6));
    }

    /**
     * @dataProvider provideInvalidProperties
     *
     * @covers ::__construct
     * @covers ::isValidPool
     * @covers ::isValidRollable
     *
     */
    public function testConstructorThrows(Pool $cup, string $compare, int $threshold): void
    {
        self::expectException(CanNotBeRolled::class);
        new Explode($cup, $compare, $threshold);
    }

    public function provideInvalidProperties(): iterable
    {
        $cup = (new Factory())->newInstance('4d6');
        return [
            'invalid comparion' => [
                'cup' => $cup,
                'compare' => 'foobar',
                'threshold' => 6,
            ],
            'greater than invalid threshold' => [
                'cup' => $cup,
                'compare' => Explode::GREATER_THAN,
                'threshold' => 0,
            ],
            'lesser than invalid threshold' => [
                'cup' => $cup,
                'compare' => Explode::LESSER_THAN,
                'threshold' => 7,
            ],
            'equals invalid threshold' => [
                'cup' => new Cup(new CustomDie(1, 1, 1)),
                'compare' => Explode::EQUALS,
                'threshold' => 1,
            ],
            'empty cup object' => [
                'cup' => new Cup(),
                'compare' => Explode::EQUALS,
                'threshold' => 2,
            ],
        ];
    }

    /**
     * @dataProvider provideExplodingModifier
     *
     * @covers ::toString
     * @covers ::getAnnotationSuffix
     *
     */
    public function testToString(Explode $roll, string $annotation): void
    {
        self::assertSame($annotation, $roll->toString());
    }

    public function provideExplodingModifier(): iterable
    {
        return [
            [
                'roll' => new Explode(new Cup(new ClassicDie(3), new ClassicDie(3), new ClassicDie(4)), Explode::EQUALS, 3),
                'annotation' => '(2D3+D4)!=3',
            ],
            [
                'roll' => new Explode(Cup::createFromRollable(4, new CustomDie(-1, -1, -1)), Explode::GREATER_THAN, 1),
                'annotation' => '4D[-1,-1,-1]!>1',
            ],
            [
                'roll' => new Explode(Cup::createFromRollable(4, new ClassicDie(6)), Explode::EQUALS, 1),
                'annotation' => '4D6!',
            ],
        ];
    }

    /**
     * @covers ::getInnerRollable
     * @throws \Bakame\DiceRoller\Exception\IllegalValue
     * @throws \Bakame\DiceRoller\Exception\UnknownAlgorithm
     * @throws \ReflectionException
     */
    public function testGetTrace(): void
    {
        $dice = $this->createMock(Rollable::class);
        $dice->method('roll')
            ->will(self::onConsecutiveCalls(2, 2, 3));

        $pool = new Cup($dice);
        $cup = new Explode($pool, Explode::EQUALS, 2);
        self::assertSame(7, $cup->roll());
        self::assertSame($pool, $cup->getInnerRollable());
    }

    /**
     * @covers ::__construct
     * @covers ::getMinimum
     * @covers ::getMaximum
     * @covers ::calculate
     * @covers ::isValid
     * @covers ::roll
     * @dataProvider validParametersProvider
     */
    public function testModifier(string $algo, int $threshold, int $min, int $max): void
    {
        $cup = new Explode($this->cup, $algo, $threshold);
        $res = $cup->roll();
        self::assertSame($min, $cup->getMinimum());
        self::assertSame($max, $cup->getMaximum());
        self::assertGreaterThanOrEqual($min, $res);
        self::assertLessThanOrEqual($max, $res);
    }

    public function validParametersProvider(): iterable
    {
        return [
            'equals' => [
                'algo' => Explode::EQUALS,
                'threshold' => 3,
                'min' => 4,
                'max' => PHP_INT_MAX,
            ],
            'greater than' => [
                'algo' => Explode::GREATER_THAN,
                'threshold' => 5,
                'min' => 4,
                'max' => PHP_INT_MAX,
            ],
            'lesser than' => [
                'algo' => Explode::LESSER_THAN,
                'threshold' => 2,
                'min' => 4,
                'max' => PHP_INT_MAX,
            ],
        ];
    }

    /**
     * @covers ::__construct
     * @covers ::getMinimum
     * @covers ::getMaximum
     * @covers ::roll
     * @covers ::calculate
     * @covers ::setProfiler
     * @covers ::setTrace
     * @covers ::getTrace
     * @covers \Bakame\DiceRoller\Profiler\LogProfiler
     * @covers \Bakame\DiceRoller\Profiler\Logger
     */
    public function testProfiler(): void
    {
        $logger = new Logger();
        $tracer = new LogProfiler($logger, LogLevel::DEBUG);
        $roll = new Explode(
            new Cup(new ClassicDie(3), new ClassicDie(3), new ClassicDie(4)),
            Explode::EQUALS,
            3
        );
        $roll->setProfiler($tracer);
        self::assertEmpty($roll->getTrace());
        $roll->roll();
        self::assertNotEmpty($roll->getTrace());
        $roll->getMaximum();
        $roll->getMinimum();
        self::assertCount(3, $logger->getLogs(LogLevel::DEBUG));
    }
}
