<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не поддерживается.']);
    exit;
}

$query = trim($_POST['query'] ?? '');
if ($query === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Пожалуйста, задайте вопрос.']);
    exit;
}




$debugInfo = [];
$answer = getHealthAssistantAnswer($query, $debugInfo);

$response = ['answer' => $answer];
$debugMode = getenv('sk-305c3820d31c4e2b892b64f56ae74ef2') === '1' || getenv('sk-305c3820d31c4e2b892b64f56ae74ef2') === '1';
if ($debugMode && !empty($debugInfo)) {
    $response['debug'] = $debugInfo;
}

echo json_encode($response);
exit;

function getHealthAssistantAnswer(string $query, array &$debugInfo = []): string
{
    $deepseekKey = getenv('https://api.deepseek.com/v1/chat/completions') ?: ($_SERVER['sk-305c3820d31c4e2b892b64f56ae74ef2'] ?? '');
    if ($deepseekKey) {
        $deepseekResponse = getDeepseekAnswer($query, $deepseekKey, $debugInfo);
        if ($deepseekResponse !== null) {
            $debugInfo['provider'] = 'deepseek';
            return $deepseekResponse;
        }
        $debugInfo['deepseek_fallback'] = true;
    }

    $openAiKey = getenv('OPENAI_API_KEY') ?: ($_SERVER['OPENAI_API_KEY'] ?? '');
    if ($openAiKey) {
        $apiResponse = getOpenAIAnswer($query, $openAiKey);
        if ($apiResponse !== null) {
            $debugInfo['provider'] = 'openai';
            return $apiResponse;
        }
        $debugInfo['openai_fallback'] = true;
    }

    $debugInfo['provider'] = 'local';
    return getLocalAssistantAnswer($query);
}

function getDeepseekAnswer(string $query, string $apiKey, array &$debugInfo = []): ?string
{
    $url = getenv('
sk-305c3820d31c4e2b892b64f56ae74ef2') ?: 'https://api.deepseek.ai/v1/complete';
    $payload = json_encode([
        'prompt' => "Пациент спрашивает: $query\nОтветь кратко на русском, без диагноза, подскажи что делать дальше.",
        'max_tokens' => 250,
        'temperature' => 0.7,
        'language' => 'ru'
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $result = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($result === false) {
        $debugInfo['deepseek_error'] = 'curl_error:' . $curlError;
        return null;
    }

    $debugInfo['deepseek_status'] = $statusCode;
    $debugInfo['deepseek_response'] = mb_substr($result, 0, 2048);

    if ($statusCode >= 400) {
        return null;
    }

    $decoded = json_decode($result, true);
    if (!is_array($decoded)) {
        $debugInfo['deepseek_error'] = 'invalid_json';
        return null;
    }

    if (!empty($decoded['text'])) {
        return trim($decoded['text']);
    }

    if (!empty($decoded['response'])) {
        return trim($decoded['response']);
    }

    if (!empty($decoded['choices'][0]['text'])) {
        return trim($decoded['choices'][0]['text']);
    }

    $debugInfo['deepseek_error'] = 'empty_response';
    return null;
}

function getOpenAIAnswer(string $query, string $apiKey): ?string
{
    $url = 'https://api.openai.com/v1/chat/completions';
    $payload = json_encode([
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Ты дружелюбный медицинский помощник. Отвечай на русском языке кратко и понятно. Не ставь диагноз, давай общие рекомендации и напоминай, что окончательное решение должен принять врач.'
            ],
            [
                'role' => 'user',
                'content' => "Пациент спрашивает: $query"
            ]
        ],
        'temperature' => 0.7,
        'max_tokens' => 250,
        'top_p' => 1,
        'n' => 1,
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $result = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($result === false || $statusCode >= 400) {
        return null;
    }

    $decoded = json_decode($result, true);
    if (!is_array($decoded) || empty($decoded['choices'][0]['message']['content'])) {
        return null;
    }

    return trim($decoded['choices'][0]['message']['content']);
}

function getLocalAssistantAnswer(string $query): string
{
    $queryLower = mb_strtolower($query, 'UTF-8');

    $patterns = [
        '/голов(н|а)/u' => 'Головная боль может быть вызвана стрессом, усталостью, обезвоживанием или напряжением. Попробуйте отдохнуть, пить чистую воду и измерить температуру. Если боль не проходит или сопровождается слабостью, лучше обратиться к врачу.',
        '/горло|кашель|запал|тонзил|фаринг/u' => 'Боль в горле и кашель часто связаны с простудой или вирусной инфекцией. Полоскания тёплой водой с солью, умеренный отдых и обильное питьё помогают. Если поднимается температура, появляется насморк или затруднённое дыхание, обратитесь к врачу.',
        '/температур|жар|озноб/u' => 'Температура может указывать на воспалительный процесс в организме. Следите за значением и общим состоянием: есть ли слабость, боль в горле, кашель или другие симптомы. При высокой температуре или ухудшении самочувствия нужно обратиться к специалисту.',
        '/боль в животе|живот|диар|понос|рвот/u' => 'Боль в животе может быть вызвана пищевым отравлением, вирусом или другими причинами. Пейте больше жидкости, избегайте тяжёлой пищи и наблюдайте за симптомами. Если боль сильная, постоянная или добавляются кровь в стуле, обратитесь к врачу.',
        '/сон|устал|слабость|энерг/u' => 'Чувство усталости и слабость часто связаны с нехваткой сна, стрессом или нехваткой витаминов. Постарайтесь соблюдать режим сна, раз в день гулять на свежем воздухе и питаться сбалансировано. Если состояние не улучшается длительное время, проконсультируйтесь с врачом.',
        '/аллерги|чихан|слез|коньюктивит/u' => 'Симптомы, похожие на аллергию, могут быть вызваны пыльцой, пылью или бытовой химией. Постарайтесь избегать раздражителей, проветривать помещение и при необходимости обратиться к врачу для точной диагностики.',
    ];

    foreach ($patterns as $pattern => $response) {
        if (preg_match($pattern, $queryLower)) {
            return $response;
        }
    }

    return 'Это предварительная медицинская подсказка. Если вы чувствуете боль, дискомфорт или другие тревожные симптомы, обязательно обратитесь к врачу. Опишите симптомы более подробно или выберите ближайшего специалиста для консультации.';
}
