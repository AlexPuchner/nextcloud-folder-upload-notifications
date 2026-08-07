<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCP\BackgroundJob;

use OCP\AppFramework\Utility\ITimeFactory;

abstract class QueuedJob {
	protected ITimeFactory $time;

	public function __construct(ITimeFactory $time) {
		$this->time = $time;
	}

	protected function setAllowParallelRuns(bool $allowParallelRuns): void {
	}

	/** @param mixed $argument */
	abstract protected function run($argument): void;
}
