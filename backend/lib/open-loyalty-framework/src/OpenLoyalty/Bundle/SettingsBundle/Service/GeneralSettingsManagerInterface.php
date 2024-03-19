<?php
/**
 * Copyright © 2017 Divante, Inc. All rights reserved.
 * See LICENSE for license details.
 */
namespace OpenLoyalty\Bundle\SettingsBundle\Service;

/**
 * Interface GeneralSettingsManagerInterface.
 */
interface GeneralSettingsManagerInterface
{
    /**
     * @return string
     */
    public function getCurrency(): string;

    /**
     * @return string
     */
    public function getTimezone(): string;

    /**
     * @return string
     */
    public function getLanguage(): string;

    /**
     * @return string
     */
    public function getProgramName(): string;

    /**
     * @return null|string
     */
    public function getProgramUrl(): ? string;

    /**
     * @return null|string
     */
    public function getConditionsUrl(): ? string;

    /**
     * @return null|string
     */
    public function FAQUrl(): ? string;

    /**
     * @return string
     */
    public function getPointsSingular(): string;

    /**
     * @return string
     */
    public function getPointsPlural(): string;

    /**
     * @return null|string
     */
    public function getHelpEmail(): ? string;

    /**
     * @return bool|null
     */
    public function isAllTimeActive(): ? bool;

    /**
     * @return int
     */
    public function getPointsDaysActive(): int;

    /**
     * @return bool
     */
    public function isReturnAvailable(): bool;

    /**
     * @return bool
     */
    public function isDeliveryCostExcluded(): bool;

    /**
     * @return string|null
     */
    public function getHotelRunner(): ?string;
}
