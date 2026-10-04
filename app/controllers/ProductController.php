<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Product CRUD. Every endpoint requires a valid JWT access token.
 */
class ProductController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
        $this->call->database();
        $this->call->helper('json');

        // Blocks the whole controller for unauthenticated requests (401)
        $this->api->require_jwt();
    }

    /** GET /api/products */
    public function index()
    {
        $rows = $this->db->table('products')->order_by('id', 'DESC')->get_all();
        $this->api->respond(['data' => array_map([$this, 'format'], $rows ?: [])]);
    }

    /** GET /api/products/{id} */
    public function show($id)
    {
        $this->api->respond(['data' => $this->format($this->find_or_404($id))]);
    }

    /** POST /api/products */
    public function store()
    {
        [$fields, $errors] = $this->validate(json_input(), true);
        if ($errors) {
            $this->api->respond(['error' => 'Validation failed', 'errors' => $errors, 'status' => 422], 422);
        }

        $this->db->table('products')->insert($fields);
        $product = $this->find_or_404($this->db->last_id());

        $this->api->respond(['message' => 'Product created', 'data' => $this->format($product)], 201);
    }

    /** PUT|PATCH /api/products/{id} */
    public function update($id)
    {
        $this->find_or_404($id);

        $is_put = ($_SERVER['REQUEST_METHOD'] ?? '') === 'PUT';
        [$fields, $errors] = $this->validate(json_input(), $is_put);
        if (!$errors && !$fields) {
            $errors['_'] = 'Nothing to update.';
        }
        if ($errors) {
            $this->api->respond(['error' => 'Validation failed', 'errors' => $errors, 'status' => 422], 422);
        }

        $this->db->table('products')->where('id', (int) $id)->update($fields);

        $this->api->respond(['message' => 'Product updated', 'data' => $this->format($this->find_or_404($id))]);
    }

    /** DELETE /api/products/{id} */
    public function destroy($id)
    {
        $this->find_or_404($id);
        $this->db->table('products')->where('id', (int) $id)->delete();
        $this->api->respond(['message' => 'Product deleted']);
    }

    // ------------------------------------------------------------

    private function find_or_404($id): array
    {
        $row = $this->db->table('products')->where('id', (int) $id)->get();
        if (!$row) {
            $this->api->respond_error('Product not found.', 404);
        }
        return $row;
    }

    /**
     * @param bool $require_all  true for POST/PUT, false for PATCH
     * @return array [fields, errors]
     */
    private function validate(array $in, bool $require_all): array
    {
        $fields = [];
        $errors = [];

        if (array_key_exists('product_name', $in) || $require_all) {
            $name = (string) ($in['product_name'] ?? '');
            if ($name === '' || mb_strlen($name) > 100) {
                $errors['product_name'] = 'Product name is required (max 100 characters).';
            } else {
                $fields['product_name'] = $name;
            }
        }

        if (array_key_exists('description', $in)) {
            $fields['description'] = $in['description'] === null ? null : (string) $in['description'];
        } elseif ($require_all) {
            $fields['description'] = null;
        }

        if (array_key_exists('price', $in) || $require_all) {
            $price = $in['price'] ?? null;
            if (!is_numeric($price) || $price < 0 || $price > 99999999.99) {
                $errors['price'] = 'Price must be a number between 0 and 99,999,999.99.';
            } else {
                $fields['price'] = number_format((float) $price, 2, '.', '');
            }
        }

        if (array_key_exists('quantity', $in) || $require_all) {
            $qty = $in['quantity'] ?? null;
            if (filter_var($qty, FILTER_VALIDATE_INT) === false || (int) $qty < 0) {
                $errors['quantity'] = 'Quantity must be a whole number, 0 or more.';
            } else {
                $fields['quantity'] = (int) $qty;
            }
        }

        return [$fields, $errors];
    }

    private function format(array $p): array
    {
        return [
            'id'           => (int) $p['id'],
            'product_name' => $p['product_name'],
            'description'  => $p['description'],
            'price'        => (float) $p['price'],
            'quantity'     => (int) $p['quantity'],
            'created_at'   => $p['created_at'],
        ];
    }
}
