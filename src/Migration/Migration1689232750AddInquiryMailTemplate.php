<?php declare(strict_types=1);

namespace Pix\Inquiry\Migration;

use DateTime;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1689232750AddInquiryMailTemplate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1689232750;
    }

    public function update(Connection $connection): void
    {
        $inquiryMailTemplateTypeId = $this->createMailTemplateType($connection);

        $this->createMailTemplate($connection, $inquiryMailTemplateTypeId);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function getLanguageIdByLocale(Connection $connection, string $locale): ?string
    {
        $sql = <<<SQL
        SELECT `language`.`id`
        FROM `language`
        INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
        WHERE `locale`.`code` = :code
        SQL;

        $languageId = $connection->executeQuery($sql, ['code' => $locale])->fetchOne();

        if (empty($languageId)) {
            return null;
        }

        return $languageId;
    }

    private function createMailTemplateType(Connection $connection): string
    {
        $mailTemplateTypeId = Uuid::randomHex();

        $enGbLangId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deDeLangId = $this->getLanguageIdByLocale($connection, 'de-DE');

        $englishName = 'Inquiry confirmation';
        $germanName = 'Anfrage bestätigung';

        $connection->executeStatement("
            INSERT IGNORE INTO `mail_template_type`
                (id, technical_name, available_entities, created_at)
            VALUES
                (:id, :technicalName, :availableEntities, :createdAt)
        ", [
            'id' => Uuid::fromHexToBytes($mailTemplateTypeId),
            'technicalName' => 'pix_inquiry_mail_template',
            'availableEntities' => json_encode(['product' => 'product']),
            'createdAt' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        if (!empty($enGbLangId)) {
            $connection->executeStatement("
            INSERT IGNORE INTO `mail_template_type_translation`
                (mail_template_type_id, language_id, name, created_at)
            VALUES
                (:mailTemplateTypeId, :languageId, :name, :createdAt)
            ", [
                'mailTemplateTypeId' => Uuid::fromHexToBytes($mailTemplateTypeId),
                'languageId' => $enGbLangId,
                'name' => $englishName,
                'createdAt' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if (!empty($deDeLangId)) {
            $connection->executeStatement("
            INSERT IGNORE INTO `mail_template_type_translation`
                (mail_template_type_id, language_id, name, created_at)
            VALUES
                (:mailTemplateTypeId, :languageId, :name, :createdAt)
            ", [
                'mailTemplateTypeId' => Uuid::fromHexToBytes($mailTemplateTypeId),
                'languageId' => $deDeLangId,
                'name' => $germanName,
                'createdAt' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        return $mailTemplateTypeId;
    }

    private function createMailTemplate(Connection $connection, string $mailTemplateTypeId): void
    {
        $mailTemplateId = Uuid::randomHex();

        $enGbLangId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deDeLangId = $this->getLanguageIdByLocale($connection, 'de-DE');

        $connection->executeStatement("
        INSERT IGNORE INTO `mail_template`
            (id, mail_template_type_id, system_default, created_at)
        VALUES
            (:id, :mailTemplateTypeId, :systemDefault, :createdAt)
        ",[
            'id' => Uuid::fromHexToBytes($mailTemplateId),
            'mailTemplateTypeId' => Uuid::fromHexToBytes($mailTemplateTypeId),
            'systemDefault' => 0,
            'createdAt' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        if (!empty($enGbLangId)) {
            $connection->executeStatement("
            INSERT IGNORE INTO `mail_template_translation`
                (mail_template_id, language_id, sender_name, subject, description, content_html, content_plain, created_at)
            VALUES
                (:mailTemplateId, :languageId, :senderName, :subject, :description, :contentHtml, :contentPlain, :createdAt)
            ",[
                'mailTemplateId' => Uuid::fromHexToBytes($mailTemplateId),
                'languageId' => $enGbLangId,
                'senderName' => '{{ shopName }}',
                'subject' => 'Your request at {{ shopName }}',
                'description' => 'Inquiry confirmation',
                'contentHtml' => $this->getContentHtmlEn(),
                'contentPlain' => $this->getContentPlainEn(),
                'createdAt' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if (!empty($deDeLangId)) {
            $connection->executeStatement("
            INSERT IGNORE INTO `mail_template_translation`
                (mail_template_id, language_id, sender_name, subject, description, content_html, content_plain, created_at)
            VALUES
                (:mailTemplateId, :languageId, :senderName, :subject, :description, :contentHtml, :contentPlain, :createdAt)
            ",[
                'mailTemplateId' => Uuid::fromHexToBytes($mailTemplateId),
                'languageId' => $deDeLangId,
                'senderName' => '{{ shopName }}',
                'subject' => 'Ihre Anfrage bei {{ shopName }}',
                'description' => 'Anfrage bestätigung',
                'contentHtml' => $this->getContentHtmlDe(),
                'contentPlain' => $this->getContentPlainDe(),
                'createdAt' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
    }

    private function getContentHtmlEn(): string
    {
        return <<<MAIL
        <div style="font-family:arial; font-size:12px;">
        
        {% set currencyIsoCode = order.currency.isoCode %}
        Hello {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ ' ' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},<br>
        <br>
        Thank you for your inquiry at {{ shopName }} (Number: {{ order.orderNumber }}) on {{ order.orderDateTime|format_datetime('medium', 'short', locale='en-GB') }}.<br>
        <br>
        <strong>Information on your inquiry:</strong><br>
        <br>
        
        <table border="0" style="font-family:Arial, Helvetica, sans-serif; font-size:12px;">
            <tr>
                <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Prod. no.</strong></td>
                <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Product image</strong></td>
                <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Description</strong></td>
                <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Quantities</strong></td>
                <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Price</strong></td>
                <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Total</strong></td>
            </tr>
        
            {% for lineItem in order.nestedLineItems %}
                {% set nestingLevel = 0 %}
                {% set nestedItem = lineItem %}
                {% block lineItem %}
                <tr>
                    <td>{% if nestedItem.payload.productNumber is defined %}{{ nestedItem.payload.productNumber|u.wordwrap(80) }}{% endif %}</td>
                    <td>{% if nestedItem.cover is defined and nestedItem.cover is not null %}<img src="{{ nestedItem.cover.url }}" width="75" height="auto"/>{% endif %}</td>
                    <td>
                        {% if nestingLevel > 0 %}
                            {% for i in 1..nestingLevel %}
                                <span style="position: relative;">
                                    <span style="display: inline-block;
                                        position: absolute;
                                        width: 6px;
                                        height: 20px;
                                        top: 0;
                                        border-left:  2px solid rgba(0, 0, 0, 0.15);
                                        margin-left: {{ i * 10 }}px;"></span>
                                </span>
                            {% endfor %}
                        {% endif %}
        
                        <div{% if nestingLevel > 0 %} style="padding-left: {{ (nestingLevel + 1) * 10 }}px"{% endif %}>
                            {{ nestedItem.label|u.wordwrap(80) }}
                        </div>
        
                        {% if nestedItem.payload.options is defined and nestedItem.payload.options|length >= 1 %}
                            <div>
                                {% for option in nestedItem.payload.options %}
                                    {{ option.group }}: {{ option.option }}
                                    {% if nestedItem.payload.options|last != option %}
                                        {{ " | " }}
                                    {% endif %}
                                {% endfor %}
                            </div>
                        {% endif %}
        
                        {% if nestedItem.payload.features is defined and nestedItem.payload.features|length >= 1 %}
                            {% set referencePriceFeatures = nestedItem.payload.features|filter(feature => feature.type == 'referencePrice') %}
                            {% if referencePriceFeatures|length >= 1 %}
                                {% set referencePriceFeature = referencePriceFeatures|first %}
                                <div>
                                    {{ referencePriceFeature.value.purchaseUnit }} {{ referencePriceFeature.value.unitName }}
                                    ({{ referencePriceFeature.value.price|currency(currencyIsoCode) }}* / {{ referencePriceFeature.value.referenceUnit }} {{ referencePriceFeature.value.unitName }})
                                </div>
                            {% endif %}
                        {% endif %}
                    </td>
                    <td style="text-align: center">{{ nestedItem.quantity }}</td>
                    <td>{{ nestedItem.unitPrice|currency(currencyIsoCode) }}</td>
                    <td>{{ nestedItem.totalPrice|currency(currencyIsoCode) }}</td>
                </tr>
        
                    {% if nestedItem.children.count > 0 %}
                        {% set nestingLevel = nestingLevel + 1 %}
                        {% for lineItem in nestedItem.children %}
                            {% set nestedItem = lineItem %}
                            {{ block('lineItem') }}
                        {% endfor %}
                    {% endif %}
                {% endblock %}
            {% endfor %}
        </table>
        
        {% set delivery = order.deliveries.first %}
        
        {% set displayRounded = order.totalRounding.interval != 0.01 or order.totalRounding.decimals != order.itemRounding.decimals %}
        {% set decimals = order.totalRounding.decimals %}
        {% set total = order.price.totalPrice %}
        {% if displayRounded %}
            {% set total = order.price.rawTotal %}
            {% set decimals = order.itemRounding.decimals %}
        {% endif %}
        <p>
            <br>
            <br>
            {% for shippingCost in order.deliveries %}
                Shipping costs: {{ shippingCost.shippingCosts.totalPrice|currency(currencyIsoCode) }}<br>
            {% endfor %}
        
            Net total: {{ order.amountNet|currency(currencyIsoCode) }}<br>
            {% for calculatedTax in order.price.calculatedTaxes %}
                {% if order.taxStatus is same as('net') %}plus{% else %}including{% endif %} {{ calculatedTax.taxRate }}% VAT. {{ calculatedTax.tax|currency(currencyIsoCode) }}<br>
            {% endfor %}
            {% if not displayRounded %}<strong>{% endif %}Total gross: {{ total|currency(currencyIsoCode,decimals=decimals) }}{% if not displayRounded %}</strong>{% endif %}<br>
            {% if displayRounded %}
                <strong>Rounded total gross: {{ order.price.totalPrice|currency(currencyIsoCode,decimals=order.totalRounding.decimals) }}</strong><br>
            {% endif %}
            <br>
        
            {% if delivery %}
                <strong>Selected shipping type:</strong> {{ delivery.shippingMethod.translated.name }}<br>
                {{ delivery.shippingMethod.translated.description }}<br>
                <br>
            {% endif %}
        
            {% set billingAddress = order.addresses.get(order.billingAddressId) %}
            <strong>Billing address:</strong><br>
            {{ billingAddress.company }}<br>
            {{ billingAddress.firstName }} {{ billingAddress.lastName }}<br>
            {{ billingAddress.street }} <br>
            {{ billingAddress.zipcode }} {{ billingAddress.city }}<br>
            {{ billingAddress.country.translated.name }}<br>
            <br>
        
            {% if delivery %}
                <strong>Shipping address:</strong><br>
                {{ delivery.shippingOrderAddress.company }}<br>
                {{ delivery.shippingOrderAddress.firstName }} {{ delivery.shippingOrderAddress.lastName }}<br>
                {{ delivery.shippingOrderAddress.street }} <br>
                {{ delivery.shippingOrderAddress.zipcode}} {{ delivery.shippingOrderAddress.city }}<br>
                {{ delivery.shippingOrderAddress.country.translated.name }}<br>
                <br>
            {% endif %}
            {% if order.orderCustomer.vatIds %}
                Your VAT-ID: {{ order.orderCustomer.vatIds|first }}
                In case of a successful order and if you are based in one of the EU countries, you will receive your goods exempt from turnover tax.<br>
            {% endif %}
            <br/>
        </p>
        <br>
        </div>
        MAIL;
    }

    private function getContentPlainEn(): string
    {
        return <<<MAIL
        {% set currencyIsoCode = order.currency.isoCode %}
        Hello {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ ' ' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},
        
        Thank you for your inquiry at {{ shopName }} (Number: {{ order.orderNumber }}) on {{ order.orderDateTime|format_datetime('medium', 'short', locale='en-GB') }}.
        
        Information on your inquiry:
        
        Pos.   Prod. No.			Product image(Alt text)			Description			Quantities			Price			Total
        
        {% for lineItem in order.lineItems %}
        {{ loop.index }}      {% if lineItem.payload.productNumber is defined %}{{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}        {% if nestedItem.cover is defined and nestedItem.cover is not null %}{{ lineItem.cover.alt }}{% endif %}        {{ lineItem.label|u.wordwrap(80) }}{% if lineItem.payload.options is defined and lineItem.payload.options|length >= 1 %}, {% for option in lineItem.payload.options %}{{ option.group }}: {{ option.option }}{% if lineItem.payload.options|last != option %}{{ " | " }}{% endif %}{% endfor %}{% endif %}{% if lineItem.payload.features is defined and lineItem.payload.features|length >= 1 %}{% set referencePriceFeatures = lineItem.payload.features|filter(feature => feature.type == 'referencePrice') %}{% if referencePriceFeatures|length >= 1 %}{% set referencePriceFeature = referencePriceFeatures|first %}, {{ referencePriceFeature.value.purchaseUnit }} {{ referencePriceFeature.value.unitName }}({{ referencePriceFeature.value.price|currency(currencyIsoCode) }}* / {{ referencePriceFeature.value.referenceUnit }} {{ referencePriceFeature.value.unitName }}){% endif %}{% endif %}
            {{ lineItem.quantity }}			{{ lineItem.unitPrice|currency(currencyIsoCode) }}			{{ lineItem.totalPrice|currency(currencyIsoCode) }}
        {% endfor %}
        
        {% set delivery = order.deliveries.first %}
        
        {% set displayRounded = order.totalRounding.interval != 0.01 or order.totalRounding.decimals != order.itemRounding.decimals %}
        {% set decimals = order.totalRounding.decimals %}
        {% set total = order.price.totalPrice %}
        {% if displayRounded %}
            {% set total = order.price.rawTotal %}
            {% set decimals = order.itemRounding.decimals %}
        {% endif %}
        
        {% for shippingCost in order.deliveries %}
            Shipping costs: {{ shippingCost.shippingCosts.totalPrice|currency(currencyIsoCode) }}<br>
        {% endfor %}
        Net total: {{ order.amountNet|currency(currencyIsoCode) }}
        {% for calculatedTax in order.price.calculatedTaxes %}
        {% if order.taxStatus is same as('net') %}plus{% else %}including{% endif %} {{ calculatedTax.taxRate }}% VAT. {{ calculatedTax.tax|currency(currencyIsoCode) }}
        {% endfor %}
        Total gross: {{ total|currency(currencyIsoCode,decimals=decimals) }}
        {% if displayRounded %}
        Rounded total gross: {{ order.price.totalPrice|currency(currencyIsoCode,decimals=order.totalRounding.decimals) }}
        {% endif %}
        
        {% if delivery %}
        Selected shipping type: {{ delivery.shippingMethod.translated.name }}
        {{ delivery.shippingMethod.translated.description }}
        {% endif %}
        
        {% set billingAddress = order.addresses.get(order.billingAddressId) %}
        Billing address:
        {{ billingAddress.company }}
        {{ billingAddress.firstName }} {{ billingAddress.lastName }}
        {{ billingAddress.street }}
        {{ billingAddress.zipcode }} {{ billingAddress.city }}
        {{ billingAddress.country.translated.name }}
        
        {% if delivery %}
        Shipping address:
        {{ delivery.shippingOrderAddress.company }}
        {{ delivery.shippingOrderAddress.firstName }} {{ delivery.shippingOrderAddress.lastName }}
        {{ delivery.shippingOrderAddress.street }}
        {{ delivery.shippingOrderAddress.zipcode}} {{ delivery.shippingOrderAddress.city }}
        {{ delivery.shippingOrderAddress.country.translated.name }}
        {% endif %}
        
        {% if order.orderCustomer.vatIds %}
        Your VAT-ID: {{ order.orderCustomer.vatIds|first }}
        In case of a successful order and if you are based in one of the EU countries, you will receive your goods exempt from turnover tax.
        {% endif %}
        MAIL;
    }

    private function getContentHtmlDe(): string
    {
        return <<<MAIL
        <div style="font-family:arial; font-size:12px;">

        {% set currencyIsoCode = order.currency.isoCode %}
        
        Hallo {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ ' ' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},<br>
        <br>
        Vielen Dank fuer Ihre Anfrage bei {{ shopName }} (Nummer: {{ order.orderNumber }}) am {{ order.orderDateTime|format_datetime('medium', 'short', locale='de-DE') }} bei uns eingegangen.<br>
        <br>
        <strong>Informationen zu Ihrer Anfrage:</strong><br>
        <br>
        
        <table border="0" style="font-family:Arial, Helvetica, sans-serif; font-size:12px;">
            <tr>
                <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Produkt-Nr.</strong></td>
                <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Produktbild</strong></td>
                <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Bezeichnung</strong></td>
                <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Menge</strong></td>
                <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Preis</strong></td>
                <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Summe</strong></td>
            </tr>
        
            {% for lineItem in order.nestedLineItems %}
                {% set nestingLevel = 0 %}
                {% set nestedItem = lineItem %}
                {% block lineItem %}
                    <tr>
                        <td>{% if nestedItem.payload.productNumber is defined %}{{ nestedItem.payload.productNumber|u.wordwrap(80) }}{% endif %}</td>
                        <td>{% if nestedItem.cover is defined and nestedItem.cover is not null %}<img src="{{ nestedItem.cover.url }}" width="75" height="auto"/>{% endif %}</td>
                        <td>
                            {% if nestingLevel > 0 %}
                                {% for i in 1..nestingLevel %}
                                    <span style="position: relative;">
                                        <span style="display: inline-block;
                                            position: absolute;
                                            width: 6px;
                                            height: 20px;
                                            top: 0;
                                            border-left:  2px solid rgba(0, 0, 0, 0.15);
                                            margin-left: {{ i * 10 }}px;"></span>
                                    </span>
                                {% endfor %}
                            {% endif %}
        
                            <div{% if nestingLevel > 0 %} style="padding-left: {{ (nestingLevel + 1) * 10 }}px"{% endif %}>
                                {{ nestedItem.label|u.wordwrap(80) }}
                            </div>
        
                            {% if nestedItem.payload.options is defined and nestedItem.payload.options|length >= 1 %}
                                <div>
                                    {% for option in nestedItem.payload.options %}
                                        {{ option.group }}: {{ option.option }}
                                        {% if nestedItem.payload.options|last != option %}
                                            {{ " | " }}
                                        {% endif %}
                                    {% endfor %}
                                </div>
                            {% endif %}
        
                            {% if nestedItem.payload.features is defined and nestedItem.payload.features|length >= 1 %}
                                {% set referencePriceFeatures = nestedItem.payload.features|filter(feature => feature.type == 'referencePrice') %}
                                {% if referencePriceFeatures|length >= 1 %}
                                    {% set referencePriceFeature = referencePriceFeatures|first %}
                                    <div>
                                        {{ referencePriceFeature.value.purchaseUnit }} {{ referencePriceFeature.value.unitName }}
                                        ({{ referencePriceFeature.value.price|currency(currencyIsoCode) }}* / {{ referencePriceFeature.value.referenceUnit }} {{ referencePriceFeature.value.unitName }})
                                    </div>
                                {% endif %}
                            {% endif %}
                        </td>
                        <td style="text-align: center">{{ nestedItem.quantity }}</td>
                        <td>{{ nestedItem.unitPrice|currency(currencyIsoCode) }}</td>
                        <td>{{ nestedItem.totalPrice|currency(currencyIsoCode) }}</td>
                    </tr>
        
                    {% if nestedItem.children.count > 0 %}
                        {% set nestingLevel = nestingLevel + 1 %}
                        {% for lineItem in nestedItem.children %}
                            {% set nestedItem = lineItem %}
                            {{ block('lineItem') }}
                        {% endfor %}
                    {% endif %}
                {% endblock %}
            {% endfor %}
        </table>
        
        {% set delivery = order.deliveries.first %}
        
        {% set displayRounded = order.totalRounding.interval != 0.01 or order.totalRounding.decimals != order.itemRounding.decimals %}
        {% set decimals = order.totalRounding.decimals %}
        {% set total = order.price.totalPrice %}
        {% if displayRounded %}
            {% set total = order.price.rawTotal %}
            {% set decimals = order.itemRounding.decimals %}
        {% endif %}
        <p>
            <br>
            <br>
            {% for shippingCost in order.deliveries %}
                Versandkosten: {{ shippingCost.shippingCosts.totalPrice|currency(currencyIsoCode) }}<br>
            {% endfor %}
            Gesamtkosten Netto: {{ order.amountNet|currency(currencyIsoCode) }}<br>
                {% for calculatedTax in order.price.calculatedTaxes %}
                    {% if order.taxStatus is same as('net') %}zzgl.{% else %}inkl.{% endif %} {{ calculatedTax.taxRate }}% MwSt. {{ calculatedTax.tax|currency(currencyIsoCode) }}<br>
                {% endfor %}
            {% if not displayRounded %}<strong>{% endif %}Gesamtkosten Brutto: {{ total|currency(currencyIsoCode,decimals=decimals) }}{% if not displayRounded %}</strong>{% endif %}<br>
            {% if displayRounded %}
                <strong>Gesamtkosten Brutto gerundet: {{ order.price.totalPrice|currency(currencyIsoCode,decimals=order.totalRounding.decimals) }}</strong><br>
            {% endif %}
            <br>
        
            {% if delivery %}
                <strong>Gewählte Versandart:</strong> {{ delivery.shippingMethod.translated.name }}<br>
                {{ delivery.shippingMethod.translated.description }}<br>
                <br>
            {% endif %}
        
            {% set billingAddress = order.addresses.get(order.billingAddressId) %}
            <strong>Rechnungsadresse:</strong><br>
            {{ billingAddress.company }}<br>
            {{ billingAddress.firstName }} {{ billingAddress.lastName }}<br>
            {{ billingAddress.street }} <br>
            {{ billingAddress.zipcode }} {{ billingAddress.city }}<br>
            {{ billingAddress.country.translated.name }}<br>
            <br>
        
            {% if delivery %}
                <strong>Lieferadresse:</strong><br>
                {{ delivery.shippingOrderAddress.company }}<br>
                {{ delivery.shippingOrderAddress.firstName }} {{ delivery.shippingOrderAddress.lastName }}<br>
                {{ delivery.shippingOrderAddress.street }} <br>
                {{ delivery.shippingOrderAddress.zipcode}} {{ delivery.shippingOrderAddress.city }}<br>
                {{ delivery.shippingOrderAddress.country.translated.name }}<br>
                <br>
            {% endif %}
            {% if order.orderCustomer.vatIds %}
                Ihre Umsatzsteuer-ID: {{ order.orderCustomer.vatIds|first }}
                Bei erfolgreicher Prüfung und sofern Sie aus dem EU-Ausland
                bestellen, erhalten Sie Ihre Ware umsatzsteuerbefreit. <br>
            {% endif %}
        </p>
        <br>
        </div>

        MAIL;
    }

    private function getContentPlainDe(): string
    {
        return <<<MAIL
        {% set currencyIsoCode = order.currency.isoCode %}
        Hallo {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ ' ' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},
        
        Vielen Dank fuer Ihre Anfrage bei {{ shopName }} (Nummer: {{ order.orderNumber }}) am {{ order.orderDateTime|format_datetime('medium', 'short', locale='de-DE') }} bei uns eingegangen.
        
        Informationen zu Ihrer Anfrage:
        
        Pos.   Artikel-Nr.			Produktbild(Alt-Text) 			Beschreibung			Menge			Preis			Summe
        
        {% for lineItem in order.lineItems %}
        {{ loop.index }}      {% if lineItem.payload.productNumber is defined %}{{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}      {% if nestedItem.cover is defined and nestedItem.cover is not null %}{{ lineItem.cover.alt }}{% endif %}        {{ lineItem.label|u.wordwrap(80) }}{% if lineItem.payload.options is defined and lineItem.payload.options|length >= 1 %}, {% for option in lineItem.payload.options %}{{ option.group }}: {{ option.option }}{% if lineItem.payload.options|last != option %}{{ " | " }}{% endif %}{% endfor %}{% endif %}{% if lineItem.payload.features is defined and lineItem.payload.features|length >= 1 %}{% set referencePriceFeatures = lineItem.payload.features|filter(feature => feature.type == 'referencePrice') %}{% if referencePriceFeatures|length >= 1 %}{% set referencePriceFeature = referencePriceFeatures|first %}, {{ referencePriceFeature.value.purchaseUnit }} {{ referencePriceFeature.value.unitName }}({{ referencePriceFeature.value.price|currency(currencyIsoCode) }}* / {{ referencePriceFeature.value.referenceUnit }} {{ referencePriceFeature.value.unitName }}){% endif %}{% endif %}
            {{ lineItem.quantity }}			{{ lineItem.unitPrice|currency(currencyIsoCode) }}			{{ lineItem.totalPrice|currency(currencyIsoCode) }}
        {% endfor %}
        
        {% set delivery = order.deliveries.first %}
        
        {% set displayRounded = order.totalRounding.interval != 0.01 or order.totalRounding.decimals != order.itemRounding.decimals %}
        {% set decimals = order.totalRounding.decimals %}
        {% set total = order.price.totalPrice %}
        {% if displayRounded %}
            {% set total = order.price.rawTotal %}
            {% set decimals = order.itemRounding.decimals %}
        {% endif %}
        
        {% for shippingCost in order.deliveries %}
            Versandkosten: {{ shippingCost.shippingCosts.totalPrice|currency(currencyIsoCode) }}<br>
        {% endfor %}
        Gesamtkosten Netto: {{ order.amountNet|currency(currencyIsoCode) }}
        {% for calculatedTax in order.price.calculatedTaxes %}
        {% if order.taxStatus is same as('net') %}zzgl.{% else %}inkl.{% endif %} {{ calculatedTax.taxRate }}% MwSt. {{ calculatedTax.tax|currency(currencyIsoCode) }}
        {% endfor %}
        Gesamtkosten Brutto: {{ total|currency(currencyIsoCode,decimals=decimals) }}
        {% if displayRounded %}
        Gesamtkosten Brutto gerundet: {{ order.price.totalPrice|currency(currencyIsoCode,decimals=order.totalRounding.decimals) }}
        {% endif %}
        
        {% if delivery %}
        Gewählte Versandart: {{ delivery.shippingMethod.translated.name }}
        {{ delivery.shippingMethod.translated.description }}
        {% endif %}
        
        {% set billingAddress = order.addresses.get(order.billingAddressId) %}
        Rechnungsadresse:
        {{ billingAddress.company }}
        {{ billingAddress.firstName }} {{ billingAddress.lastName }}
        {{ billingAddress.street }}
        {{ billingAddress.zipcode }} {{ billingAddress.city }}
        {{ billingAddress.country.translated.name }}
        
        {% if delivery %}
        Lieferadresse:
        {{ delivery.shippingOrderAddress.company }}
        {{ delivery.shippingOrderAddress.firstName }} {{ delivery.shippingOrderAddress.lastName }}
        {{ delivery.shippingOrderAddress.street }}
        {{ delivery.shippingOrderAddress.zipcode}} {{ delivery.shippingOrderAddress.city }}
        {{ delivery.shippingOrderAddress.country.translated.name }}
        {% endif %}
        
        {% if order.orderCustomer.vatIds %}
        Ihre Umsatzsteuer-ID: {{ order.orderCustomer.vatIds|first }}
        Bei erfolgreicher Prüfung und sofern Sie aus dem EU-Ausland
        bestellen, erhalten Sie Ihre Ware umsatzsteuerbefreit.
        {% endif %}
        MAIL;
    }
}
