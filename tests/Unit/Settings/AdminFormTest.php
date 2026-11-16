<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Adrian Dymorz <dev@adrian.dymorz.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Laudatio\Tests\Unit\Settings;

use OCA\Laudatio\Exception\ValidationException;
use OCA\Laudatio\Service\EventService;
use OCA\Laudatio\Service\SettingsService;
use OCA\Laudatio\Settings\AdminForm;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AdminFormTest extends TestCase {
	private SettingsService&MockObject $settings;
	private IUser&MockObject $user;
	private AdminForm $form;

	#[\Override]
	protected function setUp(): void {
		$this->settings = $this->createMock(SettingsService::class);
		$this->user = $this->createMock(IUser::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('search')->willReturn([
			$this->group('staff-b', 'Staff'),
			$this->group('admin', 'admin'),
			$this->group('hr', 'Human Resources'),
			$this->group('lookalike', 'Administrators only'),
			$this->group('staff-a', 'Staff'),
		]);

		$this->form = new AdminForm($this->settings, $this->createMock(EventService::class), $groupManager, $l10n);
	}

	public function testManagerOptionsAreUniqueLabelsOfTheGroups(): void {
		$field = $this->managerField();

		$this->assertSame([
			'Administrators only',
			'Administrators only (lookalike)',
			'Human Resources',
			'Staff (staff-a)',
			'Staff (staff-b)',
		], $field['options']);
		$this->assertSame('Administrators only', $field['placeholder']);
	}

	public function testManagerValueIsTheLabelOfTheGroup(): void {
		$this->settings->method('getManagerGroup')->willReturnOnConsecutiveCalls('', 'hr', 'staff-a', 'deleted');

		$this->assertSame('Administrators only', $this->form->getValue(SettingsService::MANAGER_GROUP, $this->user));
		$this->assertSame('Human Resources', $this->form->getValue(SettingsService::MANAGER_GROUP, $this->user));
		$this->assertSame('Staff (staff-a)', $this->form->getValue(SettingsService::MANAGER_GROUP, $this->user));
		$this->assertSame('deleted', $this->form->getValue(SettingsService::MANAGER_GROUP, $this->user));
	}

	public function testSavingAManagerLabelStoresTheGroupId(): void {
		$saved = [];
		$this->settings->method('setManagerGroup')->willReturnCallback(static function (string $groupId) use (&$saved): void {
			$saved[] = $groupId;
		});

		foreach (['Human Resources', 'Staff (staff-b)', 'Administrators only (lookalike)', 'Administrators only', null] as $label) {
			$this->form->setValue(SettingsService::MANAGER_GROUP, $label, $this->user);
		}

		$this->assertSame(['hr', 'staff-b', 'lookalike', '', ''], $saved);
	}

	public function testSavingAnUnknownManagerLabelFails(): void {
		$this->settings->expects($this->never())->method('setManagerGroup');
		$this->expectException(ValidationException::class);

		$this->form->setValue(SettingsService::MANAGER_GROUP, 'Staff', $this->user);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function managerField(): array {
		foreach ($this->form->getSchema()['fields'] as $field) {
			if ($field['id'] === SettingsService::MANAGER_GROUP) {
				return $field;
			}
		}
		$this->fail('The schema has no manager group field');
	}

	private function group(string $gid, string $displayName): IGroup&MockObject {
		$group = $this->createMock(IGroup::class);
		$group->method('getGID')->willReturn($gid);
		$group->method('getDisplayName')->willReturn($displayName);
		return $group;
	}
}
