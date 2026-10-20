<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideFrontendUi\Test\Integration;

use Magento\TestFramework\Session\SessionStartChecker;

/**
 * The integration framework's session start check, recording what asked for each session start
 *
 * Every session object asks this check when it is built, so each entry is one session the code under test started.
 */
class RecordingSessionStartChecker extends SessionStartChecker
{
    /**
     * Frames left out of a recorded chain: object creation, interception and the session's own start
     */
    private const PLUMBING = '~(ObjectManager|Interceptor|Interception|SessionManager|RecordingSessionStartChecker)~';

    /**
     * @var list<string> The call chain behind each start, outermost frame last; `new X` marks an object being built
     */
    public array $callers = [];

    /**
     * @inheritDoc
     */
    public function check(): bool
    {
        $frames = [];
        foreach (debug_backtrace(0, 160) as $frame) {
            $isFactory = str_contains($frame['class'] ?? '', 'ObjectManager\\Factory');
            $created = $isFactory && $frame['function'] === 'create' ? ($frame['args'][0] ?? null) : null;
            if (is_string($created)) {
                $frames[] = 'new ' . $created;
            } elseif (isset($frame['class']) && preg_match(self::PLUMBING, $frame['class']) !== 1) {
                $frames[] = $frame['class'] . '::' . $frame['function'];
            }
        }
        $this->callers[] = implode(' < ', $frames);

        return parent::check();
    }
}
