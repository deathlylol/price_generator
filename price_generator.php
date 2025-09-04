<?php

require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Exception;

/**
 * Генератор ценников из Excel файлов
 */
class PriceTagGenerator
{
    private $excelPath;
    private $resultsPath;
    private $templatesPath;
    private $assetsPath;

    public function __construct()
    {
        $this->excelPath = 'excel/';
        $this->resultsPath = 'results/';
        $this->templatesPath = 'templates/';
        $this->assetsPath = 'assets/';
        
        // Создаем директории если их нет
        if (!is_dir($this->resultsPath)) {
            mkdir($this->resultsPath, 0755, true);
        }
    }

    /**
     * Основной метод для генерации списков ценников
     */
    public function generateAll($mode)
    {
        $excelFiles = [
            'accessories' => 'accessories.xlsx',
            'promotions' => 'promotions.xlsx', 
            'simple' => 'simple.xlsx',
            'simple_accessories' => 'simple_accessories.xlsx'
        ];
        
        if ($mode === 'simple-list') {
            // Список simple ценников используя оригинальный шаблон
            echo "Создаем список simple ценников используя шаблон...\n";
            $this->generateSimplePriceTagsList($excelFiles['simple']);
        } elseif ($mode === 'promotions-list') {
            // Список promotions ценников используя оригинальный шаблон
            echo "Создаем список promotions ценников используя шаблон...\n";
            $this->generatePromotionsPriceTagsList($excelFiles['promotions']);
        } elseif ($mode === 'accessories-list') {
            // Список accessories ценников используя оригинальный шаблон
            echo "Создаем список accessories ценников используя шаблон...\n";
            $this->generateAccessoriesPriceTagsList($excelFiles['accessories']);
        } elseif ($mode === 'simple-accessories-list') {
            // Список simple_accessories ценников используя оригинальный шаблон
            echo "Создаем список simple_accessories ценников используя шаблон...\n";
            $this->generateSimpleAccessoriesPriceTagsList($excelFiles['simple_accessories']);
        } else {
            echo "Неизвестный режим: {$mode}\n";
            echo "Доступные режимы: simple-list, promotions-list, accessories-list, simple-accessories-list\n";
            return false;
        }
        
        echo "Генерация ценников завершена!\n";
        return true;
    }


    /**
     * Парсинг данных из Excel
     */
    private function parseExcelData($worksheet)
    {
        $data = [];
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        
        // Получаем заголовки
        $headers = [];
        for ($col = 'A'; $col <= $highestColumn; $col++) {
            $headers[$col] = $worksheet->getCell($col . '1')->getValue();
        }
        
        // Парсим данные
        for ($row = 2; $row <= $highestRow; $row++) {
            $rowData = [];
            foreach ($headers as $col => $header) {
                if ($header) {
                    $rowData[$header] = $worksheet->getCell($col . $row)->getValue();
                }
            }
            
            // Проверяем, что строка не пустая
            if (!empty(array_filter($rowData, function($value) {
                return $value !== null && $value !== '';
            }))) {
                $data[] = $rowData;
            }
        }
        
        return $data;
    }

    /**
     * Генерация HTML ценника
     */
    private function generateSinglePriceTag($type, $item)
    {
        $templateFile = $this->templatesPath . $type . '/index.html';
        
        if (!file_exists($templateFile)) {
            return $this->generateSimplePriceTag($type, $item);
        }
        
        $html = file_get_contents($templateFile);
        
        // Заполняем шаблон данными
        switch ($type) {
            case 'accessories':
                $html = $this->fillAccessoriesTemplate($html, $item);
                break;
            case 'promotions':
                $html = $this->fillPromotionsTemplate($html, $item);
                break;
            case 'simple':
                $html = $this->fillSimpleTemplate($html, $item);
                break;
            case 'simple_accessories':
                $html = $this->fillSimpleAccessoriesTemplate($html, $item);
                break;
        }
        
        // Встраиваем CSS стили
        $html = $this->inlineCssStyles($html, $type);
        
        // Обновляем пути к изображениям
        $html = $this->updateImagePaths($html);
        
        return $html;
    }

    /**
     * Заполнение шаблона аксессуаров
     */
    private function fillAccessoriesTemplate($html, $item)
    {
        $replacements = [
            '{{Название}}' => isset($item['Название']) ? htmlspecialchars($item['Название']) : '',
            '{{Цена}}' => isset($item['Цена']) ? $this->formatPrice($item['Цена']) : '',
            '{{Старая цена}}' => isset($item['Старая цена']) ? $this->formatPrice($item['Старая цена']) : '',
            '{{Рассрочка}}' => isset($item['Рассрочка']) ? htmlspecialchars($item['Рассрочка']) : ''
        ];
        
        // Сначала пытаемся заменить плейсхолдеры, если они есть в шаблоне
        $htmlAfterPlaceholders = str_replace(array_keys($replacements), array_values($replacements), $html);

        // Определяем, были ли плейсхолдеры в шаблоне
        $placeholdersWereUsed = $htmlAfterPlaceholders !== $html;

        if ($placeholdersWereUsed) {
            $html = $htmlAfterPlaceholders;

            // Убираем блоки с пустыми данными (по классам old-price / installment)
        if (empty($item['Старая цена'])) {
            $html = preg_replace('/<div[^>]*class="[^"]*old-price[^"]*"[^>]*>.*?<\/div>/s', '', $html);
        }
        if (empty($item['Рассрочка'])) {
            $html = preg_replace('/<div[^>]*class="[^"]*installment[^"]*"[^>]*>.*?<\/div>/s', '', $html);
            }

            return $html;
        }

        // Если в шаблоне НЕТ плейсхолдеров (как в текущем templates/accessories),
        // выполняем точечные подстановки по текстовым узлам шаблона

        // Название товара — заменяем содержимое заголовка
        if (!empty($item['Название'])) {
            $productName = htmlspecialchars($item['Название']);
            $html = preg_replace(
                '/(<p\s+class="text-3">\s*<span\s+class="text-white">)(.*?)(<\/span>\s*<\/p>)/su',
                '$1' . preg_quote($productName, '/') . '$3',
                $html
            );
        }

        // Текущая цена — блок <p class="text-5"><span class="text-white">3 600 000 сум</span></p>
        if (!empty($item['Цена'])) {
            $currentPrice = $this->formatPrice($item['Цена']);
            $html = preg_replace(
                '/(<p\s+class="text-5">\s*<span\s+class="text-white">)(.*?)(<\/span>\s*<\/p>)/su',
                '$1' . preg_quote($currentPrice, '/') . '$3',
                $html
            );
        }

        // Старая цена — блок с классом frame-17-6, внутри <p class="text-7">2 400 000 сум</p>
        if (!empty($item['Старая цена'])) {
            $oldPrice = $this->formatPrice($item['Старая цена']);
            $html = preg_replace(
                '/(<div[^>]*class="[^"]*frame-17-6[^"]*"[^>]*>.*?<p\s+class="text-7"><span\s+class="text-white">)(.*?)(<\/span><\/p>.*?<\/div>)/su',
                '$1' . preg_quote($oldPrice, '/') . '$3',
                $html
            );
        } else {
            // Если старой цены нет — удаляем весь блок frame-17-6
            $html = preg_replace('/<div[^>]*class="[^"]*frame-17-6[^"]*"[^>]*>.*?<\/div>/su', '', $html);
        }

        // Рассрочка — блок frame-15-8, внутри <p class="text-9">от 250 000 сум/мес</p>
        if (!empty($item['Рассрочка'])) {
            $installmentRaw = htmlspecialchars($item['Рассрочка']);
            $html = preg_replace(
                '/(<div[^>]*class="[^"]*frame-15-8[^"]*"[^>]*>.*?<p\s+class="text-9"><span\s+class="text-white">)(.*?)(<\/span><\/p>.*?<\/div>)/su',
                '$1' . preg_quote($installmentRaw, '/') . '$3',
                $html
            );
        } else {
            // Если рассрочки нет — удаляем блок frame-15-8
            $html = preg_replace('/<div[^>]*class="[^"]*frame-15-8[^"]*"[^>]*>.*?<\/div>/su', '', $html);
        }
        
        return $html;
    }

    /**
     * Заполнение шаблона simple_accessories
     */
    private function fillSimpleAccessoriesTemplate($html, $item)
    {
        // Простая замена плейсхолдеров для simple_accessories
        $replacements = [
            '{{Название}}' => isset($item['Название']) ? htmlspecialchars($item['Название']) : 'Название товара',
            '{{Цена}}' => isset($item['Цена']) ? $this->formatPrice($item['Цена']) : '—'
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $html);
    }

    /**
     * Заполнение шаблона акций
     */
    private function fillPromotionsTemplate($html, $item)
    {
        $replacements = [
            '{{Название товара}}' => isset($item['Название товара']) ? htmlspecialchars($item['Название товара']) : '',
            '{{Камера }}' => isset($item['Камера ']) ? htmlspecialchars($item['Камера ']) : '',
            '{{Дисплей}}' => isset($item['Дисплей']) ? htmlspecialchars($item['Дисплей']) : '',
            '{{Батарея}}' => isset($item['Батарея']) ? htmlspecialchars($item['Батарея']) : '',
            '{{Память}}' => isset($item['Память']) ? htmlspecialchars($item['Память']) : '',
            '{{Старая Цена}}' => isset($item['Старая Цена']) ? $this->formatPrice($item['Старая Цена']) : '',
            '{{Цена без рассрочки}}' => isset($item['Цена без рассрочки']) ? $this->formatPrice($item['Цена без рассрочки']) : '',
            '{{Цена с рассрочкой}}' => isset($item['Цена с рассрочкой']) ? $this->formatPrice($item['Цена с рассрочкой']) : ''
        ];
        
        $htmlAfterPlaceholders = str_replace(array_keys($replacements), array_values($replacements), $html);
        $placeholdersWereUsed = $htmlAfterPlaceholders !== $html;
        
        if ($placeholdersWereUsed) {
            $html = $htmlAfterPlaceholders;

        if (empty($item['Старая Цена'])) {
            $html = preg_replace('/<div[^>]*class="[^"]*old-price[^"]*"[^>]*>.*?<\/div>/s', '', $html);
        }
        if (empty($item['Цена с рассрочкой'])) {
            $html = preg_replace('/<div[^>]*class="[^"]*installment[^"]*"[^>]*>.*?<\/div>/s', '', $html);
            }
            return $html;
        }

        // Без плейсхолдеров — замены по тексту шаблона
        if (!empty($item['Название товара'])) {
            $name = htmlspecialchars($item['Название товара']);
            $html = preg_replace(
                '/(<p\s+class="text-7"><span\s+class="text-rgb-30-30-30">)(.*?)(<\/span><\/p>)/su',
                '$1' . preg_quote($name, '/') . '$3',
                $html
            );
        }

        // Характеристики
        if (!empty($item['Камера '])) {
            $camera = htmlspecialchars($item['Камера ']);
            $html = preg_replace(
                '/(<p\s+class="text-16"><span\s+class="text-rgb-30-30-30">)(.*?)(<\/span><\/p>)/su',
                '$1' . preg_quote($camera, '/') . '$3',
                $html,
                1
            );
        }
        if (!empty($item['Дисплей'])) {
            $display = htmlspecialchars($item['Дисплей']);
            $html = preg_replace(
                '/(<p\s+class="text-22"><span\s+class="text-rgb-30-30-30">)(.*?)(<\/span><\/p>)/su',
                '$1' . preg_quote($display, '/') . '$3',
                $html,
                1
            );
        }
        if (!empty($item['Батарея'])) {
            $battery = htmlspecialchars($item['Батарея']);
            $html = preg_replace(
                '/(<p\s+class="text-29"><span\s+class="text-rgb-30-30-30">)(.*?)(<\/span><\/p>)/su',
                '$1' . preg_quote($battery, '/') . '$3',
                $html,
                1
            );
        }
        if (!empty($item['Память'])) {
            $memory = htmlspecialchars($item['Память']);
            $html = preg_replace(
                '/(<p\s+class="text-35"><span\s+class="text-rgb-30-30-30">)(.*?)(<\/span><\/p>)/su',
                '$1' . preg_quote($memory, '/') . '$3',
                $html,
                1
            );
        }

        // Цены
        if (!empty($item['Старая Цена'])) {
            $oldPrice = $this->formatPrice($item['Старая Цена']);
            // в шаблоне старая цена отображается в span.old-price
            $html = preg_replace(
                '/(<span\s+class="old-price">)(.*?)(<\/span>)/su',
                '$1' . preg_quote($oldPrice, '/') . '$3',
                $html
            );
        } else {
            // удалить span.old-price
            $html = preg_replace('/<span\s+class="old-price">.*?<\/span>/su', '', $html);
        }

        if (!empty($item['Цена без рассрочки'])) {
            $price = $this->formatPrice($item['Цена без рассрочки']);
            $html = preg_replace(
                '/(<p\s+class="text-47"><span\s+class="text-white">)(.*?)(<\/span><\/p>)/su',
                '$1' . preg_quote($price, '/') . '$3',
                $html,
                1
            );
        }

        if (!empty($item['Цена с рассрочкой'])) {
            $installment = $this->formatPrice($item['Цена с рассрочкой']);
            $html = preg_replace(
                '/(<p\s+class="text-43"><span\s+class="text-white">)(.*?)(<\/span><\/p>)/su',
                '$1' . preg_quote($installment, '/') . '$3',
                $html,
                1
            );
        } else {
            // убрать блок с рассрочкой: это div.frame-17-38 содержащий frame-22-39
            $html = preg_replace('/<div[^>]*class="[^"]*frame-17-38[^"]*"[^>]*>.*?<\/div>/su', '', $html);
        }
        
        return $html;
    }

    /**
     * Заполнение простого шаблона
     */
    protected function fillSimpleTemplate($html, $item)
    {
        
        // Заменяем содержимое по CSS классам для надежности
        
        // Название товара - заменяем содержимое в text-7
        if (isset($item['Название товара']) && !empty($item['Название товара'])) {
            $html = preg_replace(
                '/(<p\s+class="text-7"><span\s+class="text-rgb-30-30-30">)(.*?)(<\/span><\/p>)/su',
                '$1' . htmlspecialchars($item['Название товара']) . '$3',
                $html
            );
        }
        
        // Характеристики - заменяем по классам
        if (isset($item['Камера ']) && !empty($item['Камера '])) {
            $html = preg_replace(
                '/(<p\s+class="text-16"><span\s+class="text-rgb-30-30-30">)(.*?)(<\/span><\/p>)/su',
                '$1' . htmlspecialchars($item['Камера ']) . '$3',
                $html
            );
        }
        
        if (isset($item['Дисплей']) && !empty($item['Дисплей'])) {
            $html = preg_replace(
                '/(<p\s+class="text-22"><span\s+class="text-rgb-30-30-30">)(.*?)(<\/span><\/p>)/su',
                '$1' . htmlspecialchars($item['Дисплей']) . '$3',
                $html
            );
        }
        
        if (isset($item['Батарея']) && !empty($item['Батарея'])) {
            $html = preg_replace(
                '/(<p\s+class="text-29"><span\s+class="text-rgb-30-30-30">)(.*?)(<\/span><\/p>)/su',
                '$1' . htmlspecialchars($item['Батарея']) . '$3',
                $html
            );
        }
        
        if (isset($item['Память']) && !empty($item['Память'])) {
            $html = preg_replace(
                '/(<p\s+class="text-35"><span\s+class="text-rgb-30-30-30">)(.*?)(<\/span><\/p>)/su',
                '$1' . htmlspecialchars($item['Память']) . '$3',
                $html
            );
        }
        
        // Сначала заменяем старую цену в span.old-price на временное значение
        if (isset($item['Старая Цена']) && !empty($item['Старая Цена'])) {
            $oldPrice = $this->formatPrice($item['Старая Цена']);
            // Заменяем содержимое в span.old-price на временное значение
            $html = str_replace('<span class="old-price">12 000 000 сум</span>', '<span class="old-price">TEMP_OLD_PRICE</span>', $html);
        } else {
            // Удаляем span.old-price если старая цена не указана
            $html = str_replace('<span class="old-price">12 000 000 сум</span>', '', $html);
        }
        
        // Затем заменяем остальные цены - заменяем ВСЕ вхождения статического текста глобально
        if (isset($item['Цена без рассрочки']) && !empty($item['Цена без рассрочки'])) {
            $price = $this->formatPrice($item['Цена без рассрочки']);
            // Заменяем ВСЕ вхождения статического текста "12 000 000 сум" глобально
            $html = str_replace('12 000 000 сум', $price, $html);
        }
        
        // В конце заменяем временное значение на реальную старую цену
        if (isset($item['Старая Цена']) && !empty($item['Старая Цена'])) {
            $oldPrice = $this->formatPrice($item['Старая Цена']);
            $html = str_replace('<span class="old-price">TEMP_OLD_PRICE</span>', '<span class="old-price">' . $oldPrice . '</span>', $html);
        }
        
        if (isset($item['Цена с рассрочкой']) && !empty($item['Цена с рассрочкой'])) {
            $installmentPrice = $this->formatPrice($item['Цена с рассрочкой']);
            // Заменяем ВСЕ вхождения статического текста "1 130 000" глобально
            $html = str_replace('1 130 000', $installmentPrice, $html);
        }
        
        return $html;
    }

    /**
     * Генерация простого ценника если шаблон не найден
     */
    private function generateSimplePriceTag($type, $item)
    {
        $html = '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ценник</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .price-tag { border: 2px solid #333; padding: 20px; max-width: 300px; }
        .name { font-size: 18px; font-weight: bold; margin-bottom: 10px; }
        .price { font-size: 24px; color: #e74c3c; font-weight: bold; }
        .specs { margin: 10px 0; font-size: 14px; }
    </style>
</head>
<body>
    <div class="price-tag">';
        
        if (isset($item['Название товара'])) {
            $html .= '<div class="name">' . htmlspecialchars($item['Название товара']) . '</div>';
        } elseif (isset($item['Название'])) {
            $html .= '<div class="name">' . htmlspecialchars($item['Название']) . '</div>';
        }
        
        if (isset($item['Цена без рассрочки'])) {
            $html .= '<div class="price">' . $this->formatPrice($item['Цена без рассрочки']) . '</div>';
        } elseif (isset($item['Цена'])) {
            $html .= '<div class="price">' . $this->formatPrice($item['Цена']) . '</div>';
        }
        
        $html .= '</div>
</body>
</html>';
        
        return $html;
    }

    /**
     * Форматирование цены
     */
    protected function formatPrice($price)
    {
        // Убираем "сум" если есть, чтобы обработать только число
        $price = str_replace(' сум', '', $price);
        $price = trim($price);
        
        // Если это число, форматируем его с пробелами
        if (is_numeric($price)) {
            $price = number_format($price, 0, '.', ' ');
        }
        
        // Добавляем "сум" обратно
        $price .= ' сум';
        
        return $price;
    }

    /**
     * Встраивание CSS стилей в HTML
     */
    private function inlineCssStyles($html, $type)
    {
        $cssFile = $this->templatesPath . $type . '/styles.css';
        
        if (file_exists($cssFile)) {
            $css = file_get_contents($cssFile);
            $html = str_replace('</head>', '<style>' . $css . '</style></head>', $html);
        }
        
        return $html;
    }

    /**
     * Обновление путей к изображениям
     */
    private function updateImagePaths($html)
    {
        // Заменяем пути к изображениям на относительные
        $html = str_replace('../assets/images/', 'images/', $html);
        $html = str_replace('assets/images/', 'images/', $html);
        
        return $html;
    }
    

    /**
     * Копирование изображений в папку результатов
     */
    private function copyImagesToResults($outputDir)
    {
        $imagesDir = $outputDir . 'images/';
        if (!is_dir($imagesDir)) {
            mkdir($imagesDir, 0755, true);
        }
        
        $sourceImagesDir = $this->assetsPath . 'images/';
        if (is_dir($sourceImagesDir)) {
            $this->copyDirectory($sourceImagesDir, $imagesDir);
        }
    }

    /**
     * Копирование директории
     */
    private function copyDirectory($source, $destination)
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        
        $dir = opendir($source);
        while (($file = readdir($dir)) !== false) {
            if ($file != '.' && $file != '..') {
                $sourcePath = $source . $file;
                $destPath = $destination . $file;
                
                if (is_dir($sourcePath)) {
                    $this->copyDirectory($sourcePath, $destPath);
                } else {
                    copy($sourcePath, $destPath);
                }
            }
        }
        closedir($dir);
    }


    

    
    

    

    

    

    
    
    /**
     * Генерация имени файла
     */
    private function generateFilename($item, $index)
    {
        $name = 'price_tag';
        
        if (isset($item['Название'])) {
            $name = $this->sanitizeFilename($item['Название']);
        } elseif (isset($item['Название товара'])) {
            $name = $this->sanitizeFilename($item['Название товара']);
        } elseif (isset($item['ID товара (QR Code)'])) {
            $name = 'id_' . $this->sanitizeFilename($item['ID товара (QR Code)']);
        } elseif (isset($item['Артикул'])) {
            $name = 'art_' . $this->sanitizeFilename($item['Артикул']);
        }
        
        return $name . '_' . ($index + 1) . '.html';
    }

    /**
     * Очистка имени файла от недопустимых символов
     */
    private function sanitizeFilename($filename)
    {
        $filename = preg_replace('/[^a-zA-Z0-9а-яА-Я\s\-_]/u', '', $filename);
        $filename = preg_replace('/\s+/', '_', $filename);
        return substr($filename, 0, 50);
    }

    

    /**
     * Генерация списка simple ценников используя оригинальный шаблон
     */
    private function generateSimplePriceTagsList($filename)
    {
        $filePath = $this->excelPath . $filename;
        
        if (!file_exists($filePath)) {
            echo "Файл {$filename} не найден\n";
            return;
        }
        
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $this->parseExcelData($worksheet);
            
            if (empty($data)) {
                echo "Нет данных для генерации simple ценников\n";
                return;
            }
            
            echo "Загружено simple: " . count($data) . " товаров\n";
            
            // Создаем HTML используя оригинальный шаблон
            $html = $this->createSimplePriceTagsListHtml($data);
            
            // Сохраняем в файл
            $outputFile = $this->resultsPath . 'simple/simple_price_tags_list.html';
            file_put_contents($outputFile, $html);
            
            echo "Создан список simple ценников: {$outputFile}\n";
            echo "Всего товаров: " . count($data) . "\n";
            
        } catch (Exception $e) {
            echo "Ошибка при обработке файла {$filename}: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Создание HTML для списка simple ценников используя оригинальный шаблон
     */
    private function createSimplePriceTagsListHtml($priceTags)
    {
        
        // Загружаем оригинальный шаблон
        $templateFile = $this->templatesPath . 'simple/index.html';
        $cssFile = $this->templatesPath . 'simple/styles.css';
        
        if (!file_exists($templateFile)) {
            echo "Шаблон simple не найден\n";
            return '';
        }
        
        $template = file_get_contents($templateFile);
        $css = '';
        if (file_exists($cssFile)) {
            $css = file_get_contents($cssFile);
        }
        
        // Создаем HTML с встроенными стилями
        $html = '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Список Simple ценников</title>
    <link href="https://fonts.googleapis.com/css?family=Inter&display=swap" rel="stylesheet">
    <style>' . $css . '</style>
</head>
<body>
    <div class="node-1">';
        
        // Группируем товары по 2 (1 ряд по 2)
        $itemsPerBlock = 2;
        $blocks = array_chunk($priceTags, $itemsPerBlock);
        
        foreach ($blocks as $blockIndex => $blockItems) {
            // Создаем flex-block с 2 ценниками (1 ряд)
            $html .= '<div class="flex-block">';
            
            // Добавляем 2 товара в ряд, используя оригинальный шаблон
            for ($i = 0; $i < count($blockItems); $i++) {
                // Заполняем шаблон данными товара
                $itemHtml = $this->fillSimpleTemplate($template, $blockItems[$i]);
                // Извлекаем только первый блок ценника (div.block) из заполненного шаблона
                if (preg_match('/<div\s+class="block"[^>]*>.*?<\/div>\s*<\/div>\s*<\/div>\s*<\/div>\s*<\/div>/s', $itemHtml, $matches)) {
                    $html .= $matches[0];
                }
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Создание блока ценника для simple шаблона
     */
    private function createSimplePriceTagBlock($item)
    {
        
        // Сначала создаем базовый HTML с правильными данными
        $html = '<div class="block">
            <div class="frame-4-2">
                <div class="frame-25-3">
                    <img src="images/node-4.svg" class="node-4" alt="Товар" />
                </div>
                <div class="frame-26-5">
                    <div class="frame-33-6">
                        <p class="text-7"><span class="text-rgb-30-30-30">';
        
        // Название товара
        if (isset($item['Название товара']) && !empty($item['Название товара'])) {
            $html .= htmlspecialchars($item['Название товара']);
        } else {
            $html .= 'Название товара';
        }
        
        $html .= '</span></p>
                    </div>
                </div>
            </div>
            <div class="frame-24-8">
                <div class="frame-2-9">
                    <div class="frame-6-10">
                        <div class="frame-7-11">
                            <div class="icons-sbi-12">
                                <img src="images/vector-13.svg" class="vector-13" alt="vector" />
                            </div>
                            <div class="frame-12-14">
                                <p class="text-15"><span class="text-rgb-107-107-107">Камера</span></p>
                                <p class="text-16"><span class="text-rgb-30-30-30">';
        
        // Характеристика камеры
        if (isset($item['Камера ']) && !empty($item['Камера '])) {
            $html .= htmlspecialchars($item['Камера ']);
        } else {
            $html .= '—';
        }
        
        $html .= '</span></p>
                            </div>
                        </div>
                        <div class="frame-8-17">
                            <div class="icons-sbi-18">
                                <img src="images/vector-19.svg" class="vector-19" alt="vector" />
                            </div>
                            <div class="frame-12-20">
                                <p class="text-21"><span class="text-rgb-107-107-107">Дисплей</span></p>
                                <p class="text-22"><span class="text-rgb-30-30-30">';
        
        // Характеристика дисплея
        if (isset($item['Дисплей']) && !empty($item['Дисплей'])) {
            $html .= htmlspecialchars($item['Дисплей']);
        } else {
            $html .= '—';
        }
        
        $html .= '</span></p>
                            </div>
                        </div>
                    </div>
                    <div class="frame-5-23">
                        <div class="frame-7-24">
                            <div class="icons-sbi-25">
                                <img src="images/vector-26.svg" class="vector-26" alt="vector" />
                            </div>
                            <div class="frame-12-27">
                                <p class="text-28"><span class="text-rgb-107-107-107">Батарея</span></p>
                                <p class="text-29"><span class="text-rgb-30-30-30">';
        
        // Характеристика батареи
        if (isset($item['Батарея']) && !empty($item['Батарея'])) {
            $html .= htmlspecialchars($item['Батарея']);
        } else {
            $html .= '—';
        }
        
        $html .= '</span></p>
                            </div>
                        </div>
                        <div class="frame-8-30">
                            <div class="icons-sbi-31">
                                <img src="images/vector-32.svg" class="vector-32" alt="vector" />
                            </div>
                            <div class="frame-12-33">
                                <p class="text-34"><span class="text-rgb-107-107-107">Память</span></p>
                                <p class="text-35"><span class="text-rgb-30-30-30">';
        
        // Характеристика памяти
        if (isset($item['Память']) && !empty($item['Память'])) {
            $html .= htmlspecialchars($item['Память']);
        } else {
            $html .= '—';
        }
        
        $html .= '</span></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="frame-23-36">
                    <div class="frame-20-37">
                        <div class="frame-17-38">
                            <div class="frame-22-39">
                                <p class="text-40"><span class="text-white">Цена в рассрочку:</span></p>
                                <div class="frame-32-41">
                                    <p class="text-42"><span class="text-white">от</span></p>
                                    <p class="text-43"><span class="text-white">';
        
        // Цена в рассрочку - если пустая, показываем прочерк
        if (isset($item['Цена в рассорочку']) && !empty($item['Цена в рассорочку'])) {
            $html .= $this->formatPrice($item['Цена в рассорочку']);
        } else {
            // Если цены в рассрочку нет, показываем прочерк
            $html .= '—';
        }
        
        $html .= '</span></p>
                                    <p class="text-44"><span class="text-white">сум/мес</span></p>
                                </div>
                            </div>
                        </div>
                        <div class="frame-15-45">';
        
        // Старая цена - если есть, показываем
        if (isset($item['Старая Цена']) && !empty($item['Старая Цена'])) {
            $oldPrice = $this->formatPrice($item['Старая Цена']);
            $html .= '<span class="old-price">' . $oldPrice . '</span>';
        }
        
        $html .= '<p class="text-46"><span class="text-white">Цена без рассрочки:</span></p>
                            <p class="text-47"><span class="text-white">';
        
        // Цена без рассрочки - если пустая, показываем прочерк
        if (isset($item['Цена без рассрочки']) && !empty($item['Цена без рассрочки'])) {
            $html .= $this->formatPrice($item['Цена без рассрочки']);
        } else {
            // Если цены без рассрочки нет, показываем прочерк
            $html .= '—';
        }
        
        $html .= '</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
        
        return $html;
    }
    
    /**
     * Генерация списка simple_accessories ценников используя оригинальный шаблон
     */
    private function generateSimpleAccessoriesPriceTagsList($filename)
    {
        $filePath = $this->excelPath . $filename;
        
        if (!file_exists($filePath)) {
            echo "Файл {$filename} не найден\n";
            return;
        }
        
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $this->parseExcelData($worksheet);
        } catch (Exception $e) {
            echo "Ошибка при обработке файла {$filename}: " . $e->getMessage() . "\n";
            return;
        }
        
        if (empty($data)) {
            echo "Нет данных для генерации simple_accessories ценников\n";
            return;
        }
        
        echo "Загружено simple_accessories: " . count($data) . " товаров\n";
        
        // Отладочная информация - показываем доступные поля
        if (!empty($data)) {
            echo "Доступные поля в первом товаре:\n";
            foreach (array_keys($data[0]) as $field) {
                echo "  - " . $field . "\n";
            }
            echo "Первый товар:\n";
            foreach ($data[0] as $field => $value) {
                echo "  " . $field . ": " . $value . "\n";
            }
        }
        
        // Создаем HTML используя оригинальный шаблон
        $html = $this->createSimpleAccessoriesPriceTagsListHtml($data);
        
        // Сохраняем в файл
        $outputFile = $this->resultsPath . 'simple_accessories/simple_accessories_price_tags_list.html';
        file_put_contents($outputFile, $html);
        
        echo "Создан список simple_accessories ценников: {$outputFile}\n";
        echo "Всего товаров: " . count($data) . "\n";
        echo "\n💡 СОВЕТ: Для создания PDF откройте HTML файл в браузере и используйте Печать -> Сохранить как PDF\n";
        echo "   При печати выберите размер страницы A4 и установите масштаб 100%\n";
    }
    

    
    /**
     * Генерация списка accessories ценников используя оригинальный шаблон
     */
        private function generateAccessoriesPriceTagsList($filename)
    {
        $filePath = $this->excelPath . $filename;
        
        if (!file_exists($filePath)) {
            echo "Файл {$filename} не найден\n";
            return;
        }
        
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $this->parseExcelData($worksheet);
        } catch (Exception $e) {
            echo "Ошибка при обработке файла {$filename}: " . $e->getMessage() . "\n";
            return;
        }
        
        if (empty($data)) {
            echo "Нет данных для генерации accessories ценников\n";
            return;
        }
        
        echo "Загружено accessories: " . count($data) . " товаров\n";
        
        // Отладочная информация - показываем доступные поля
        if (!empty($data)) {
            echo "Доступные поля в первом товаре:\n";
            foreach (array_keys($data[0]) as $field) {
                echo "  - " . $field . "\n";
            }
            echo "Первый товар:\n";
            foreach ($data[0] as $field => $value) {
                echo "  " . $field . ": " . $value . "\n";
            }
        }
        
        // Создаем HTML используя оригинальный шаблон
        $html = $this->createAccessoriesPriceTagsListHtml($data);
        
        // Сохраняем в файл
        $outputFile = $this->resultsPath . 'accessories/accessories_price_tags_list.html';
        file_put_contents($outputFile, $html);
        
        echo "Создан список accessories ценников: {$outputFile}\n";
        echo "Всего товаров: " . count($data) . "\n";
    }
    
    /**
     * Генерация списка promotions ценников используя оригинальный шаблон
     */
    private function generatePromotionsPriceTagsList($filename)
    {
        $filePath = $this->excelPath . $filename;
        
        if (!file_exists($filePath)) {
            echo "Файл {$filename} не найден\n";
            return;
        }
        
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $this->parseExcelData($worksheet);
            
            if (empty($data)) {
                echo "Нет данных для генерации promotions ценников\n";
                return;
            }
            
            echo "Загружено promotions: " . count($data) . " товаров\n";
            
            // Отладочная информация - показываем доступные поля
            if (!empty($data)) {
                echo "Доступные поля в первом товаре:\n";
                foreach (array_keys($data[0]) as $field) {
                    echo "  - " . $field . "\n";
                }
                echo "Первый товар:\n";
                foreach ($data[0] as $field => $value) {
                    echo "  " . $field . ": " . $value . "\n";
                }
            }
            
            // Создаем HTML используя оригинальный шаблон
            $html = $this->createPromotionsPriceTagsListHtml($data);
            
            // Сохраняем в файл
            $outputFile = $this->resultsPath . 'promotions/promotions_price_tags_list.html';
            file_put_contents($outputFile, $html);
            
            echo "Создан список promotions ценников: {$outputFile}\n";
            echo "Всего товаров: " . count($data) . "\n";
            
        } catch (Exception $e) {
            echo "Ошибка при обработке файла {$filename}: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Создание HTML для списка simple_accessories ценников используя оригинальный шаблон
     */
    private function createSimpleAccessoriesPriceTagsListHtml($priceTags)
    {
        // Загружаем только CSS
        $cssFile = $this->templatesPath . 'simple_accessories/styles.css';
        $css = '';
        if (file_exists($cssFile)) {
            $css = file_get_contents($cssFile);
        }
        
        // Создаем HTML с встроенными стилями
        $html = '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Список Simple Accessories ценников</title>
    <link href="https://fonts.googleapis.com/css?family=Inter&display=swap" rel="stylesheet">
    <style>' . $css . '</style>
</head>
<body>
    <div class="node-1">';
        
        // Группируем товары по 1 (1 ценник на страницу при печати)
        $itemsPerBlock = 1;
        $blocks = array_chunk($priceTags, $itemsPerBlock);
        
        foreach ($blocks as $blockIndex => $blockItems) {
            // Создаем flex-block с 1 ценником (1 ценник на страницу)
            $html .= '<div class="flex-block">';
            
            // Добавляем 1 товар в flex-block
            for ($i = 0; $i < count($blockItems); $i++) {
                $html .= $this->createSimpleAccessoriesPriceTagBlock($blockItems[$i]);
        }
        
        $html .= '</div>';
        }
        
        $html .= '</div>
</body>
</html>';
        
        return $html;
    }

    /**
     * Создание HTML для списка accessories ценников используя оригинальный шаблон
     */
    private function createAccessoriesPriceTagsListHtml($priceTags)
    {
        // Загружаем оригинальный шаблон
        $templateFile = $this->templatesPath . 'accessories/index.html';
        $cssFile = $this->templatesPath . 'accessories/styles.css';
        
        if (!file_exists($templateFile)) {
            echo "Шаблон accessories не найден\n";
            return '';
        }
        
        $template = file_get_contents($templateFile);
        $css = '';
        if (file_exists($cssFile)) {
            $css = file_get_contents($cssFile);
        }
        
        // Создаем HTML с встроенными стилями
        $html = '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Список Accessories ценников</title>
    <link href="https://fonts.googleapis.com/css?family=Inter&display=swap" rel="stylesheet">
    <style>' . $css . '</style>
</head>
<body>
    <div class="node-1">';
        
        // Группируем товары по 2 (1 ряд)
        $itemsPerBlock = 2;
        $blocks = array_chunk($priceTags, $itemsPerBlock);
        
        foreach ($blocks as $blockIndex => $blockItems) {
            // Создаем flex-block с 2 ценниками (1 ряд)
            $html .= '<div class="flex-block">';
            
            // Добавляем 2 товара в flex-block
            for ($i = 0; $i < count($blockItems); $i++) {
                $html .= $this->createAccessoriesPriceTagBlock($blockItems[$i]);
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Создание HTML для списка promotions ценников используя оригинальный шаблон
     */
    private function createPromotionsPriceTagsListHtml($priceTags)
    {
        // Загружаем оригинальный шаблон
        $templateFile = $this->templatesPath . 'promotions/index.html';
        $cssFile = $this->templatesPath . 'promotions/styles.css';
        
        if (!file_exists($templateFile)) {
            echo "Шаблон promotions не найден\n";
            return '';
        }
        
        $template = file_get_contents($templateFile);
        $css = '';
        if (file_exists($cssFile)) {
            $css = file_get_contents($cssFile);
        }
        
        // Создаем HTML с встроенными стилями
        $html = '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Список Promotions ценников</title>
    <link href="https://fonts.googleapis.com/css?family=Inter&display=swap" rel="stylesheet">
    <style>' . $css . '</style>
</head>
<body>
    <div class="node-1 sale">';
        
        // Группируем товары по 2 (1 ряд)
        $itemsPerBlock = 2;
        $blocks = array_chunk($priceTags, $itemsPerBlock);
        
        foreach ($blocks as $blockIndex => $blockItems) {
            // Создаем flex-block с 2 ценниками (1 ряд)
            $html .= '<div class="flex-block">';
            
            // Добавляем 2 товара в flex-block
            for ($i = 0; $i < count($blockItems); $i++) {
                $html .= $this->createPromotionsPriceTagBlock($blockItems[$i]);
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Создание блока ценника для simple_accessories шаблона
     */
    private function createSimpleAccessoriesPriceTagBlock($item)
    {
        // Создаем HTML напрямую, только блок без node-1 и flex-block
        $html = '<div class="block">
            <div class="frame-30-2">
                <p class="text-3"><span class="text-white">';
        
        // Название товара
        if (isset($item['Название']) && !empty($item['Название'])) {
            $html .= htmlspecialchars($item['Название']);
        } else {
            $html .= 'Название товара';
        }
        
        $html .= '</span></p>
            </div>
            <div class="frame-31-4">
                <div class="frame-17-6">
                    <p class="text-7">
                        <span class="text-white">';
        
        // Цена
        if (isset($item['Цена']) && !empty($item['Цена'])) {
            $html .= $this->formatPrice($item['Цена']);
        } else {
            $html .= '—';
        }
        
        $html .= '</span></p>
                </div>
            </div>
        </div>';
        
        return $html;
    }
    

    


    
    /**
     * Создание блока ценника для accessories шаблона
     */
    private function createAccessoriesPriceTagBlock($item)
    {
        $html = '<div class="block accessories-block">
            <div class="frame-30-2">
                <p class="text-3"><span class="text-white">';
        
        // Название товара
        if (isset($item['Название']) && !empty($item['Название'])) {
            $html .= htmlspecialchars($item['Название']);
        } else {
            $html .= 'Название товара';
        }
        
        $html .= '</span></p>
            </div>
            <div class="frame-31-4">
                <p class="text-5"><span class="text-white">';
        
        // Старая цена (если есть)
        if (isset($item['Старая цена']) && !empty($item['Старая цена'])) {
            $html .= $this->formatPrice($item['Старая цена']);
        } else {
            $html .= '—';
        }
        
        $html .= '</span></p>
                <div class="frame-17-6">
                    <p class="text-7"><span class="text-white">';
        
        // Текущая цена
        if (isset($item['Цена']) && !empty($item['Цена'])) {
            $html .= $this->formatPrice($item['Цена']);
        } else {
            $html .= '—';
        }
        
        $html .= '</span></p>
                </div>
                <div class="frame-15-8">
                    <p class="text-9"><span class="text-white">';
        
        // Рассрочка
        if (isset($item['Рассрочка']) && !empty($item['Рассрочка'])) {
            $html .= htmlspecialchars($item['Рассрочка']);
        } else {
            $html .= '—';
        }
        
        $html .= '</span></p>
                </div>
            </div>
        </div>';
        
        return $html;
    }
    
    /**
     * Создание блока ценника для promotions шаблона
     */
    private function createPromotionsPriceTagBlock($item)
    {
        $html = '<div class="block">
            <div class="frame-4-2">
                <div class="frame-25-3">
                    <img src="images/node-4.svg" class="node-4" alt="Товар" />
                </div>
                <div class="frame-26-5">
                    <div class="frame-33-6">
                        <p class="text-7"><span class="text-rgb-30-30-30">';
        
        // Название товара
        if (isset($item['Название товара']) && !empty($item['Название товара'])) {
            $html .= htmlspecialchars($item['Название товара']);
        } else {
            $html .= 'Название товара';
        }
        
        $html .= '</span></p>
                    </div>
                </div>
            </div>
            <div class="frame-24-8">
                <div class="frame-2-9">
                    <div class="frame-6-10">
                        <div class="frame-7-11">
                            <div class="icons-sbi-12">
                                <img src="images/vector-13.svg" class="vector-13" alt="vector" />
                            </div>
                            <div class="frame-12-14">
                                <p class="text-15"><span class="text-rgb-107-107-107">Камера</span></p>
                                <p class="text-16"><span class="text-rgb-30-30-30">';
        
        // Характеристика камеры
        if (isset($item['Камера ']) && !empty($item['Камера '])) {
            $html .= htmlspecialchars($item['Камера ']);
        } else {
            $html .= '—';
        }
        
        $html .= '</span></p>
                            </div>
                        </div>
                        <div class="frame-8-17">
                            <div class="icons-sbi-18">
                                <img src="images/vector-19.svg" class="vector-19" alt="vector" />
                            </div>
                            <div class="frame-12-20">
                                <p class="text-21"><span class="text-rgb-107-107-107">Дисплей</span></p>
                                <p class="text-22"><span class="text-rgb-30-30-30">';
        
        // Характеристика дисплея
        if (isset($item['Дисплей']) && !empty($item['Дисплей'])) {
            $html .= htmlspecialchars($item['Дисплей']);
        } else {
            $html .= '—';
        }
        
        $html .= '</span></p>
                            </div>
                        </div>
                    </div>
                    <div class="frame-5-23">
                        <div class="frame-7-24">
                            <div class="icons-sbi-25">
                                <img src="images/vector-26.svg" class="vector-26" alt="vector" />
                            </div>
                            <div class="frame-12-27">
                                <p class="text-28"><span class="text-rgb-107-107-107">Батарея</span></p>
                                <p class="text-29"><span class="text-rgb-30-30-30">';
        
        // Характеристика батареи
        if (isset($item['Батарея']) && !empty($item['Батарея'])) {
            $html .= htmlspecialchars($item['Батарея']);
        } else {
            $html .= '—';
        }
        
        $html .= '</span></p>
                            </div>
                        </div>
                        <div class="frame-8-30">
                            <div class="icons-sbi-31">
                                <img src="images/vector-32.svg" class="vector-32" alt="vector" />
                            </div>
                            <div class="frame-12-33">
                                <p class="text-34"><span class="text-rgb-107-107-107">Память</span></p>
                                <p class="text-35"><span class="text-rgb-30-30-30">';
        
        // Характеристика памяти
        if (isset($item['Память']) && !empty($item['Память'])) {
            $html .= htmlspecialchars($item['Память']);
        } else {
            $html .= '—';
        }
        
        $html .= '</span></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="frame-23-36">
                    <div class="frame-20-37">
                        <div class="frame-17-38">
                            <div class="frame-22-39">
                                <p class="text-40"><span class="text-white">Цена в рассрочку:</span></p>
                                <div class="frame-32-41">
                                    <p class="text-42"><span class="text-white">от</span></p>
                                    <p class="text-43"><span class="text-white">';
        
        // Цена в рассрочку - если пустая, показываем прочерк
        if (isset($item['Цена с рассрочкой']) && !empty($item['Цена с рассрочкой'])) {
            $html .= $this->formatPrice($item['Цена с рассрочкой']);
        } else {
            // Если цены в рассрочку нет, показываем прочерк
            $html .= '—';
        }
        
        $html .= '</span></p>
                                    <p class="text-44"><span class="text-white">сум/мес</span></p>
                                </div>
                            </div>
                        </div>
                        <div class="frame-15-45">';
        
        // Старая цена (если есть)
        if (isset($item['Старая Цена']) && !empty($item['Старая Цена'])) {
            $html .= '<span class="old-price">' . $this->formatPrice($item['Старая Цена']) . '</span>';
        }
        
        $html .= '<p class="text-46">
                            <span class="text-white">Цена без рассрочки:</span>
                        </p>
                        <p class="text-47"><span class="text-white">';
        
        // Цена без рассрочки - если пустая, показываем прочерк
        if (isset($item['Цена без рассрочки']) && !empty($item['Цена без рассрочки'])) {
            $html .= $this->formatPrice($item['Цена без рассрочки']);
        } else {
            // Если цены без рассрочки нет, показываем прочерк
            $html .= '—';
        }
        
        $html .= '</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
        
        return $html;
    }
}

// Запуск генератора
if (php_sapi_name() === 'cli') {
    $generator = new PriceTagGenerator();
    
    // Режим генерации: 'print' - только лист для печати, 'individual' - отдельные ценники
    if (!isset($argv[1])) {
        echo "Использование:\n";
        echo "php price_generator.php simple-list - генерация списка simple ценников используя шаблон\n";
        echo "php price_generator.php promotions-list - генерация списка promotions ценников используя шаблон\n";
        echo "php price_generator.php accessories-list - генерация списка accessories ценников используя шаблон\n";
        echo "php price_generator.php simple-accessories-list - генерация списка simple_accessories ценников используя шаблон\n";
        exit(1);
    }
    
    $mode = $argv[1];
    
    if ($mode === 'simple-list') {
        echo "Режим: Генерация списка simple ценников используя шаблон\n";
        $generator->generateAll('simple-list');
    } elseif ($mode === 'promotions-list') {
        echo "Режим: Генерация списка promotions ценников используя шаблон\n";
        $generator->generateAll('promotions-list');
    } elseif ($mode === 'accessories-list') {
        echo "Режим: Генерация списка accessories ценников используя шаблон\n";
        $generator->generateAll('accessories-list');
    } elseif ($mode === 'simple-accessories-list') {
        echo "Режим: Генерация списка simple_accessories ценников используя шаблон\n";
        $generator->generateAll('simple-accessories-list');
    } else {
        echo "Неизвестный режим: {$mode}\n";
        echo "Использование:\n";
        echo "  php price_generator.php simple-list - генерация списка simple ценников используя шаблон\n";
        echo "  php price_generator.php promotions-list - генерация списка promotions ценников используя шаблон\n";
        echo "  php price_generator.php accessories-list - генерация списка accessories ценников используя шаблон\n";
        echo "  php price_generator.php simple-accessories-list - генерация списка simple_accessories ценников используя шаблон\n";
        exit(1);
    }
} else {
    echo "Этот скрипт предназначен для запуска из командной строки.\n";
}
?>
