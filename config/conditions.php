<?php

/*
|--------------------------------------------------------------------------
| DONATE+ — CATEGORY-AWARE CONDITIONS & EXPIRY RULES
|--------------------------------------------------------------------------
|
| Single source of truth for:
|   - which "condition" options are valid for each category
|   - which categories require an expiry / best-before date
|
| Included by add-donation.php and admin-donation-edit.php, and used both
| for server-side validation and for building the live dropdown on the
| forms. Keeping it in one place means the form, the admin edit page and
| the validation can never disagree.
|
*/


/*
| Condition option sets. Each category is mapped to one of these keys.
*/

$CONDITION_SETS = [
    "durable"  => ["New", "Like New", "Good", "Used"],
    "food"     => ["Fresh", "Packaged & Sealed", "Home-cooked / Leftover"],
    "medical"  => ["Sealed / Unopened", "Unused"],
    "fallback" => ["New", "Usable", "Used"],
];


/*
| Map each category NAME (exactly as stored in categories.name) to a set.
| A category not listed here falls back to the "fallback" set.
*/

$CATEGORY_CONDITION_MAP = [
    "Clothing"           => "durable",
    "Books & Stationery" => "durable",
    "Furniture"          => "durable",
    "Electronics"        => "durable",
    "Toys & Games"       => "durable",
    "Household Items"    => "durable",
    "Food & Groceries"   => "food",
    "Medical Supplies"   => "medical",
    "Other"              => "fallback",
];


/*
| Categories that require an expiry / best-before date.
*/

$EXPIRY_CATEGORIES = ["Food & Groceries", "Medical Supplies"];


/*
| Allowed condition list for a given category name.
*/

function conditions_for_category($category_name)
{
    global $CONDITION_SETS, $CATEGORY_CONDITION_MAP;

    $key = $CATEGORY_CONDITION_MAP[$category_name] ?? "fallback";

    return $CONDITION_SETS[$key] ?? $CONDITION_SETS["fallback"];
}


/*
| Does this category name need an expiry date?
*/

function category_needs_expiry($category_name)
{
    global $EXPIRY_CATEGORIES;

    return in_array($category_name, $EXPIRY_CATEGORIES, true);
}


/*
| Is $condition a valid choice for $category_name?
*/

function is_valid_condition($category_name, $condition)
{
    return in_array($condition, conditions_for_category($category_name), true);
}


/*
| Resolve a category id to its name ("" if not found).
*/

function category_name_by_id($conn, $category_id)
{
    $category_id = (int) $category_id;

    $stmt = $conn->prepare("SELECT name FROM categories WHERE id = ? LIMIT 1");

    if (!$stmt) {
        return "";
    }

    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row["name"] ?? "";
}


/*
| Build a JS map keyed by category id, e.g.
|   { "3": {"conditions":[...],"expiry":true}, ..., "": {default} }
| The add / edit forms read this to rebuild the condition dropdown and
| show or hide the expiry field when the category changes.
*/

function condition_map_json($conn)
{
    $out = [];

    $res = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $out[(string) $row["id"]] = [
                "conditions" => conditions_for_category($row["name"]),
                "expiry"     => category_needs_expiry($row["name"]),
            ];
        }
    }

    // Default used when no category is selected yet.
    $out[""] = [
        "conditions" => conditions_for_category(""),
        "expiry"     => false,
    ];

    return json_encode($out);
}
