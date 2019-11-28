<?php

/**
 * PHP Dice Roller (https://github.com/bakame-php/dice-roller/)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Bakame\DiceRoller\Tracer;

use Bakame\DiceRoller\Contract\Rollable;
use Bakame\DiceRoller\Contract\TraceContext;

final class Context implements TraceContext
{
    /**
     * @var Rollable
     */
    private $rollable;

    /**
     * @var string
     */
    private $source;

    /**
     * @var array
     */
    private $extensions;

    public function __construct(Rollable $rollable, string $source, array $extensions = [])
    {
        unset($extensions['source'], $extensions['operation'], $extensions['expression'], $extensions['result']);

        $this->rollable = $rollable;
        $this->source = $source;
        $this->extensions = $extensions;
    }

    /**
     * {@inheritDoc}
     */
    public function rollable(): Rollable
    {
        return $this->rollable;
    }

    /**
     * {@inheritDoc}
     */
    public function source(): string
    {
        return $this->source;
    }

    /**
     * {@inheritDoc}
     */
    public function extensions(): array
    {
        return $this->extensions;
    }

    /**
     * {@inheritDoc}
     */
    public function asArray(): array
    {
        return ['source' => $this->source, 'expression' => $this->rollable->expression()] + $this->extensions;
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): array
    {
        return $this->asArray();
    }
}