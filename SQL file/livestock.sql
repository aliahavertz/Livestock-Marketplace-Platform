-- FARMER TABLE
CREATE TABLE farmer (
    farmer_id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'farmer' CHECK (role = 'farmer'),
    farm_name VARCHAR(150),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    postal_code VARCHAR(20),
    phone_number VARCHAR(20),
    alternate_contact VARCHAR(20),
    profile_image TEXT,
    farm_description TEXT,
    livestock_type VARCHAR(100),
    registration_number VARCHAR(100),
    verified_status BOOLEAN DEFAULT FALSE,
    date_registered TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE customer 
ADD COLUMN IF NOT EXISTS reset_token_hash VARCHAR(64) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS reset_token_expires_at TIMESTAMP DEFAULT NULL;


-- CUSTOMER TABLE
CREATE TABLE customer (
    customer_id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'customer' CHECK (role = 'customer'),
    phone_number VARCHAR(20),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    postal_code VARCHAR(20),
    profile_image TEXT,
    preferred_livestock_type VARCHAR(100),
    account_status VARCHAR(20) DEFAULT 'active',
    date_registered TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE customer
ADD COLUMN auth_provider VARCHAR(50) DEFAULT 'local';
ADD COLUMN auth_uid VARCHAR(100) DEFAULT;

ALTER TABLE customer
ADD COLUMN verify_token VARCHAR(255);

ALTER TABLE customer
ADD COLUMN verify_status VARCHAR(20) DEFAULT 'unverified';
ADD COLUMN IF NOT EXISTS reset_token_hash VARCHAR(64) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS reset_token_expires_at TIMESTAMP DEFAULT NULL;


-- ADMIN TABLE
CREATE TABLE admin (
    admin_id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'admin' CHECK (role = 'admin'),
    phone_number VARCHAR(20),
    position VARCHAR(100),
    permissions_level VARCHAR(50),
    status VARCHAR(20) DEFAULT 'active',
    date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE administrator (
    admin_id SERIAL PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL, -- To store password_hash()
    role VARCHAR(20) DEFAULT 'staff', -- 'superadmin' or 'staff'
    status VARCHAR(20) DEFAULT 'active', -- 'active' or 'suspended'
    last_login TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- LIVESTOCK TABLE (linked to FARMER)
CREATE TABLE livestock (
    livestock_id SERIAL PRIMARY KEY,
    name VARCHAR(100),
    breed VARCHAR(100),
    age INT,
    gender VARCHAR(10),
    weight DECIMAL(10,2),
    price DECIMAL(10,2),
    health_status VARCHAR(100),
    description TEXT,
    image TEXT,
    category VARCHAR(50),
    availability_status VARCHAR(50) DEFAULT 'available',
    location VARCHAR(150),
    farmer_id INT REFERENCES farmer(farmer_id) ON DELETE CASCADE,
    date_listed TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TYPE sale_mode AS ENUM ('Fixed', 'Auction');

ALTER TABLE livestock 
ADD COLUMN sale_type sale_mode DEFAULT 'Fixed';

ALTER TABLE livestock ADD COLUMN farmer_livestock_no INTEGER;

-- BIDDING TABLE (linked to LIVESTOCK and CUSTOMER)
CREATE TABLE bidding (
    bid_id SERIAL PRIMARY KEY,
    livestock_id INT REFERENCES livestock(livestock_id) ON DELETE CASCADE,
    customer_id INT REFERENCES customer(customer_id) ON DELETE CASCADE,
    start_price DECIMAL(10,2),
    current_bid DECIMAL(10,2),
    highest_bidder_id INT REFERENCES customer(customer_id),
    min_increment DECIMAL(10,2),
    start_date TIMESTAMP,
    end_date TIMESTAMP,
    status VARCHAR(20) DEFAULT 'active',
    winner_id INT REFERENCES customer(customer_id)
);
ALTER TABLE bidding
ADD COLUMN current_bid NUMERIC(10,2),
ADD COLUMN last_bidder_id INT;


-- ORDERS TABLE (linked to CUSTOMER and LIVESTOCK)
CREATE TABLE orders (
    order_id SERIAL PRIMARY KEY,
    customer_id INT REFERENCES customer(customer_id) ON DELETE CASCADE,
    livestock_id INT REFERENCES livestock(livestock_id) ON DELETE CASCADE,
    bid_id INT REFERENCES bidding(bid_id) ON DELETE SET NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_price DECIMAL(10,2),
    status VARCHAR(50) DEFAULT 'pending',
    payment_status VARCHAR(50) DEFAULT 'unpaid',
    delivery_method VARCHAR(100),
    delivery_address TEXT
);
ALTER TABLE orders 
ADD COLUMN harvest_remarks TEXT,
ADD COLUMN selected_services VARCHAR(255),
ADD COLUMN shipping_address TEXT;
ALTER TABLE orders 
ADD COLUMN refund_reason VARCHAR(255),
ADD COLUMN refund_notes TEXT,
ADD COLUMN refund_evidence_image TEXT,
ADD COLUMN refund_requested_at TIMESTAMP,
ADD COLUMN refund_completed_at TIMESTAMP;

CREATE TYPE order_status_type AS ENUM (
  'Pending',
  'Approved',
  'Rejected'
);

ALTER TABLE orders
ADD COLUMN status order_status_type DEFAULT 'Pending';
ALTER TABLE orders 
ADD COLUMN shipping_address TEXT,
ADD COLUMN contact_name VARCHAR(255),
ADD COLUMN contact_phone VARCHAR(50);

-- PAYMENTS TABLE (linked to ORDERS)
CREATE TABLE payments (
    payment_id SERIAL PRIMARY KEY,
    order_id INT REFERENCES orders(order_id) ON DELETE CASCADE,
    amount DECIMAL(10,2),
    payment_method VARCHAR(50),
    payment_status VARCHAR(50) DEFAULT 'successful',
    transaction_id VARCHAR(100),
    receipt_url TEXT,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE payments 
ADD COLUMN cust_name VARCHAR(255),
ADD COLUMN cust_email VARCHAR(255),
ADD COLUMN cust_phone VARCHAR(50);

CREATE TABLE HarvestService (
    serviceID SERIAL PRIMARY KEY,
    farmerID INT NOT NULL,
    livestockID INT NOT NULL,
    serviceType VARCHAR(100) NOT NULL,
    description TEXT,
    serviceFee DOUBLE PRECISION NOT NULL,
    availability BOOLEAN DEFAULT TRUE,

    CONSTRAINT fk_harvest_farmer
        FOREIGN KEY (farmerID)
        REFERENCES Farmer(farmerID)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_harvest_livestock
        FOREIGN KEY (livestockID)
        REFERENCES Livestock(livestockID)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

CREATE TABLE Health (
    healthID SERIAL PRIMARY KEY,
    livestockID INT NOT NULL,
    vaccination VARCHAR(255),
    medicine VARCHAR(255),
    vitamin VARCHAR(255),
    healthDate DATE DEFAULT CURRENT_DATE,

    CONSTRAINT fk_livestock
        FOREIGN KEY (livestockID)
        REFERENCES livestock(livestock_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

CREATE TABLE HarvestService (
    service_id SERIAL PRIMARY KEY,
    farmer_id INT NOT NULL,
    livestock_id INT NOT NULL,
    serviceType VARCHAR(100) NOT NULL,
    description TEXT,
    serviceFee DOUBLE PRECISION NOT NULL,
    availability BOOLEAN DEFAULT TRUE,

    CONSTRAINT fk_farmer
        FOREIGN KEY (farmer_id)
        REFERENCES farmer(farmer_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE

    CONSTRAINT fk_livestock
        FOREIGN KEY (livestock_id)
        REFERENCES livestock(livestock_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

CREATE TABLE delivery (
    deliveryID SERIAL PRIMARY KEY,
    orderID INT NOT NULL,
    deliveryFee DECIMAL(10, 2) NOT NULL, 
    deliveryStatus VARCHAR(50) DEFAULT 'Pending',
    deliveryAddress TEXT NOT NULL,
    deliveryDate TIMESTAMP NULL,
    riderName VARCHAR(100) NULL,
    trackingNumber VARCHAR(50) NULL, 
        FOREIGN KEY (orderID) REFERENCES orders(order_id) 
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

CREATE TABLE delivery (
    deliveryid SERIAL PRIMARY KEY,
    customer_orderid INT REFERENCES customer_order(customer_orderid),
    shipping_method VARCHAR(50), 
    tracking_number VARCHAR(100),
    delivery_status VARCHAR(50) DEFAULT 'Pending', 
    delivery_fee DECIMAL(10, 2),
    courier_name VARCHAR(100), 
    estimated_arrival DATE,
    actual_arrival TIMESTAMP,
    delivery_address TEXT, 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- feedback
CREATE TABLE feedback (
    feedback_id SERIAL PRIMARY KEY, 
    customer_id INT NOT NULL, 
    livestock_id INT NOT NULL,
    farmer_id INT,
    admin_id INT,             
    feedback_message TEXT NOT NULL, 
    rating INT CHECK (rating >= 1 AND rating <= 5),
    feedback_date DATE DEFAULT CURRENT_DATE,     
    status VARCHAR(20) DEFAULT 'Pending', 
    CONSTRAINT fk_customer FOREIGN KEY (customer_id) REFERENCES customer(customer_id) ON DELETE CASCADE,
    CONSTRAINT fk_livestock FOREIGN KEY (livestock_id) REFERENCES livestock(livestock_id) ON DELETE CASCADE,
    CONSTRAINT fk_farmer FOREIGN KEY (farmer_id) REFERENCES farmer(farmer_id) ON DELETE CASCADE,
    CONSTRAINT fk_administrator FOREIGN KEY (admin_id) REFERENCES administrator(admin_id) ON DELETE SET NULL
);

ALTER TABLE feedback ADD COLUMN livestock_id INT;

ALTER TABLE feedback 
ADD CONSTRAINT fk_livestock 
FOREIGN KEY (livestock_id) 
REFERENCES livestock(livestock_id) 
ON DELETE CASCADE;

CREATE TABLE notifications (
    notification_id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,          
    user_type VARCHAR(10) NOT NULL, 
    title VARCHAR(100),
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE cart (
    cart_id SERIAL PRIMARY KEY,
    customer_id INT NOT NULL,
    livestock_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(customer_id, livestock_id)
);

CREATE TABLE auction_deposits_paid (
    payment_id SERIAL PRIMARY KEY,
    auction_id INT NOT NULL,
    customer_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) DEFAULT 'paid', 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_auction_p FOREIGN KEY(auction_id) REFERENCES auction(auction_id)
);

CREATE TABLE livestock_images (
    image_id SERIAL PRIMARY KEY,
    livestock_id INT NOT NULL,
    image_name VARCHAR(255) NOT NULL,
    CONSTRAINT fk_livestock 
        FOREIGN KEY (livestock_id) 
        REFERENCES livestock(livestock_id) 
        ON DELETE CASCADE
);

CREATE TABLE order_items (
    order_item_id SERIAL PRIMARY KEY,
    order_id INT REFERENCES orders(order_id),
    livestock_id INT REFERENCES livestock(livestock_id),
    price_at_purchase DECIMAL(10,2),
    selected_services TEXT 
);

CREATE TABLE livestock_delivery_options (
    option_id SERIAL PRIMARY KEY,
    livestock_id INTEGER REFERENCES livestock(livestock_id) ON DELETE CASCADE,
    method_name VARCHAR(100) NOT NULL, 
    fee NUMERIC(10, 2) NOT NULL DEFAULT 0.00
);
ALTER TABLE livestock_delivery_options 
ADD COLUMN max_capacity INT DEFAULT 10;

ALTER TABLE delivery 
ADD COLUMN recipient_name VARCHAR(255),
ADD COLUMN phone_number VARCHAR(20),
ADD COLUMN email VARCHAR(255),
ADD COLUMN city VARCHAR(100),
ADD COLUMN postcode VARCHAR(20),
ADD COLUMN state VARCHAR(100),
ADD COLUMN shipping_method VARCHAR(100);
ALTER TABLE notifications ADD COLUMN status_reason TEXT;
ALTER TABLE orders ADD COLUMN status_reason TEXT;