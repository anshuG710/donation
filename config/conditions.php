<?php

/*
|--------------------------------------------------------------------------
| Item Conditions by Category
|--------------------------------------------------------------------------
|
| Defines which conditions (New, Like New, Good, Used) are valid for
| each item category. Also marks which categories need expiry dates.
|
*/

/**
 * Map of category -> conditions and expiry requirement
 */
function get_conditions_map() {
    return [
        "Clothing" => [
            "conditions" => ["New", "Like New", "Good", "Used"],
            "expiry" => false
        ],
        "Food & Groceries" => [
            "conditions" => ["New"],
            "expiry" => true
        ],
        "Books & Stationery" => [
            "conditions" => ["New", "Like New", "Good", "Used"],
            "expiry" => false
        ],
        "Furniture" => [
            "conditions" => ["Like New", "Good", "Used"],
            "expiry" => false
        ],
        "Electronics" => [
            "conditions" => ["Like New", "Good", "Used"],
            "expiry" => false
        ],
        "Medical Supplies" => [
            "conditions" => ["New"],
            "expiry" => true
        ],
        "Toys & Games" => [
            "conditions" => ["New", "Like New", "Good"],
            "expiry" => false
        ],
        "Household Items" => [
            "conditions" => ["New", "Like New", "Good", "Used"],
            "expiry" => false
        ],
        "Other" => [
            "conditions" => ["New", "Like New", "Good", "Used"],
            "expiry" => false
        ]
    ];
}

/**
 * Get valid conditions for a category
 */
function conditions_for_category($category_name) {
    $map = get_conditions_map();
    return isset($map[$category_name])
        ? $map[$category_name]["conditions"]
        : ["New", "Like New", "Good", "Used"];
}

/**
 * Check if a category requires an expiry date
 */
function category_needs_expiry($category_name) {
    $map = get_conditions_map();
    return isset($map[$category_name])
        ? $map[$category_name]["expiry"]
        : false;
}

/**
 * Check if a condition is valid for a category
 */
function is_valid_condition($category_name, $condition) {
    $valid = conditions_for_category($category_name);
    return in_array($condition, $valid);
}

/**
 * Get the conditions map as JSON for JavaScript
 * Used in forms to dynamically update conditions based on category
 */
function condition_map_json($conn) {
    $map = get_conditions_map();
    $result = [];
    
    // Get all categories from database
    $categories = $conn->query("SELECT id, name FROM categories");
    
    if ($categories) {
        while ($row = $categories->fetch_assoc()) {
            $cat_name = $row["name"];
            $result[$row["id"]] = [
                "name" => $cat_name,
                "conditions" => conditions_for_category($cat_name),
                "expiry" => category_needs_expiry($cat_name)
            ];
        }
    }
    
    // Default entry
    $result[""] = [
        "name" => "",
        "conditions" => [],
        "expiry" => false
    ];
    
    return json_encode($result);
}

?>