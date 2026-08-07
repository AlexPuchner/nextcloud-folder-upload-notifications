<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OC\Hooks;

class Emitter {
	/** @param array<string, mixed> $option */
	public function emit(string $class, string $value, array $option): void {
	}

	/** @param callable $closure */
	public function listen(string $class, string $value, callable $closure): void {
	}
}
