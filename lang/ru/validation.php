<?php

declare(strict_types=1);

/*
| Russian messages for the rules the API uses; any other rule falls back to the framework's English lines.
*/
return [
    'accepted' => 'Необходимо принять поле :attribute.',
    'array' => 'Поле :attribute должно быть массивом.',
    'boolean' => 'Поле :attribute должно быть логическим значением.',
    'email' => 'Поле :attribute должно содержать корректный email.',
    'in' => 'Выбрано недопустимое значение поля :attribute.',
    'integer' => 'Поле :attribute должно быть целым числом.',
    'max' => [
        'array' => 'Поле :attribute не может содержать более :max элементов.',
        'numeric' => 'Поле :attribute не может быть больше :max.',
        'string' => 'Поле :attribute не может быть длиннее :max символов.',
    ],
    'min' => [
        'array' => 'Поле :attribute должно содержать не менее :min элементов.',
        'numeric' => 'Поле :attribute не может быть меньше :min.',
        'string' => 'Поле :attribute должно быть не короче :min символов.',
    ],
    'numeric' => 'Поле :attribute должно быть числом.',
    'required' => 'Поле :attribute обязательно для заполнения.',
    'string' => 'Поле :attribute должно быть строкой.',
    'url' => 'Поле :attribute должно содержать корректную ссылку.',

    'attributes' => [
        'email' => 'email',
        'password' => 'пароль',
        'remember' => 'запомнить меня',
        'url' => 'ссылка',
    ],
];
