-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 03, 2026 at 08:09 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `smart_cafeteria_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `counter`
--

CREATE TABLE `counter` (
  `CounterID` int(11) NOT NULL,
  `CounterName` varchar(50) NOT NULL,
  `Status` enum('Active','Inactive','Maintenance') DEFAULT 'Active',
  `Location` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `counter`
--

INSERT INTO `counter` (`CounterID`, `CounterName`, `Status`, `Location`) VALUES
(1, 'Snacks & Drinks', 'Active', 'North Wing'),
(2, 'Meals & Lunch', 'Active', 'South Wing');

-- --------------------------------------------------------

--
-- Table structure for table `counter_serves_food`
--

CREATE TABLE `counter_serves_food` (
  `CounterID` int(11) NOT NULL,
  `FoodID` int(11) NOT NULL,
  `AvailableQuantity` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `counter_serves_food`
--

INSERT INTO `counter_serves_food` (`CounterID`, `FoodID`, `AvailableQuantity`) VALUES
(1, 1, 30),
(1, 3, 50),
(1, 4, 40),
(2, 2, 60);

-- --------------------------------------------------------

--
-- Table structure for table `food`
--

CREATE TABLE `food` (
  `FoodID` int(11) NOT NULL,
  `FoodName` varchar(100) NOT NULL,
  `Category` varchar(50) NOT NULL,
  `Price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `food`
--

INSERT INTO `food` (`FoodID`, `FoodName`, `Category`, `Price`) VALUES
(1, 'Chicken Burger', 'Fast Food', 120.00),
(2, 'Beef Khichuri', 'Lunch Meal', 180.00),
(3, 'Cold Coffee', 'Beverage', 70.00),
(4, 'Egg Sandwich', 'Snacks', 50.00);

-- --------------------------------------------------------

--
-- Table structure for table `food_review`
--

CREATE TABLE `food_review` (
  `ReviewID` int(11) NOT NULL,
  `StudentID` int(11) NOT NULL,
  `FoodID` int(11) NOT NULL,
  `Rating` int(11) DEFAULT NULL CHECK (`Rating` between 1 and 5),
  `ReviewComment` text DEFAULT NULL,
  `ReviewDate` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ingredient`
--

CREATE TABLE `ingredient` (
  `IngredientID` int(11) NOT NULL,
  `IngredientName` varchar(100) NOT NULL,
  `AvailQty` decimal(10,2) NOT NULL,
  `Unit` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ingredient`
--

INSERT INTO `ingredient` (`IngredientID`, `IngredientName`, `AvailQty`, `Unit`) VALUES
(1, 'Chicken Breast', 25.50, 'kg'),
(2, 'Rice', 100.00, 'kg'),
(3, 'Milk', 40.00, 'Ltr'),
(4, 'Bread Loaf', 50.00, 'Pcs');

-- --------------------------------------------------------

--
-- Table structure for table `prep_room`
--

CREATE TABLE `prep_room` (
  `PrepID` int(11) NOT NULL,
  `FoodID` int(11) NOT NULL,
  `PrepDate` date NOT NULL,
  `QuantityPrepared` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prep_room_uses_ingredient`
--

CREATE TABLE `prep_room_uses_ingredient` (
  `PrepID` int(11) NOT NULL,
  `IngredientID` int(11) NOT NULL,
  `QuantityUsed` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sale`
--

CREATE TABLE `sale` (
  `SaleID` int(11) NOT NULL,
  `StudentID` int(11) NOT NULL,
  `SaleDate` date NOT NULL,
  `SaleTime` time NOT NULL,
  `PickupCounterID` int(11) DEFAULT NULL,
  `PickupTime` time DEFAULT NULL,
  `OrderStatus` enum('Pending','Preparing','Ready','Completed','Cancelled') DEFAULT 'Pending',
  `TotalAmount` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale`
--

INSERT INTO `sale` (`SaleID`, `StudentID`, `SaleDate`, `SaleTime`, `PickupCounterID`, `PickupTime`, `OrderStatus`, `TotalAmount`) VALUES
(1, 1, '2026-08-20', '12:30:00', 2, '13:00:00', 'Completed', 180.00),
(2, 2, '2026-08-20', '13:15:00', 1, '13:30:00', 'Completed', 190.00);

-- --------------------------------------------------------

--
-- Table structure for table `sale_item`
--

CREATE TABLE `sale_item` (
  `SaleID` int(11) NOT NULL,
  `ItemNo` int(11) NOT NULL,
  `FoodID` int(11) NOT NULL,
  `QtyPurchased` int(11) NOT NULL,
  `Subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_item`
--

INSERT INTO `sale_item` (`SaleID`, `ItemNo`, `FoodID`, `QtyPurchased`, `Subtotal`) VALUES
(1, 1, 2, 1, 180.00),
(2, 1, 1, 1, 120.00),
(2, 2, 3, 1, 70.00);

-- --------------------------------------------------------

--
-- Table structure for table `seat_reservation`
--

CREATE TABLE `seat_reservation` (
  `ReservationID` int(11) NOT NULL,
  `StudentID` int(11) NOT NULL,
  `TableNumber` int(11) NOT NULL,
  `SeatNumber` int(11) NOT NULL,
  `ReservationDate` date NOT NULL,
  `StartTime` time NOT NULL,
  `EndTime` time NOT NULL,
  `Purpose` enum('Regular Dining','Club Activity','Faculty Meeting','Event') DEFAULT 'Regular Dining',
  `Status` enum('Confirmed','Cancelled','Completed') DEFAULT 'Confirmed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `UserID` int(11) NOT NULL,
  `Position` varchar(50) NOT NULL,
  `Shift` enum('Morning','Evening','Night') NOT NULL,
  `Salary` decimal(10,2) NOT NULL,
  `CounterID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`UserID`, `Position`, `Shift`, `Salary`, `CounterID`) VALUES
(3, 'Counter Cashier', 'Morning', 18000.00, 1),
(4, 'Food Server', 'Evening', 16000.00, 2);

-- --------------------------------------------------------

--
-- Table structure for table `staff_attendance`
--

CREATE TABLE `staff_attendance` (
  `AttendanceID` int(11) NOT NULL,
  `StaffID` int(11) NOT NULL,
  `DutyDate` date NOT NULL,
  `CheckIn` time DEFAULT NULL,
  `CheckOut` time DEFAULT NULL,
  `Status` enum('Present','Absent','Late') DEFAULT 'Present'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_review`
--

CREATE TABLE `staff_review` (
  `ReviewID` int(11) NOT NULL,
  `StudentID` int(11) NOT NULL,
  `StaffID` int(11) NOT NULL,
  `Rating` int(11) DEFAULT NULL CHECK (`Rating` between 1 and 5),
  `ReviewComment` text DEFAULT NULL,
  `ReviewDate` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_task`
--

CREATE TABLE `staff_task` (
  `TaskID` int(11) NOT NULL,
  `StaffID` int(11) NOT NULL,
  `TaskDescription` varchar(255) NOT NULL,
  `TaskType` enum('Food Prep','Counter Service','Stock Refill','Cleaning','Event Service') NOT NULL,
  `AssignedDate` date NOT NULL,
  `Status` enum('Pending','In Progress','Completed') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student`
--

CREATE TABLE `student` (
  `UserID` int(11) NOT NULL,
  `Department` varchar(50) NOT NULL,
  `Batch` varchar(20) NOT NULL,
  `WalletBalance` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student`
--

INSERT INTO `student` (`UserID`, `Department`, `Batch`, `WalletBalance`) VALUES
(1, 'Finance', '28th', 1500.00),
(2, 'CSE', '27th', 800.00),
(6, 'BBA', '29th', 0.00),
(8, 'CSE', '67', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `supplier`
--

CREATE TABLE `supplier` (
  `SupplierID` int(11) NOT NULL,
  `SupplierName` varchar(100) NOT NULL,
  `Phone` varchar(20) NOT NULL,
  `Address` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supplier_supplies_ingredient`
--

CREATE TABLE `supplier_supplies_ingredient` (
  `SupplierID` int(11) NOT NULL,
  `IngredientID` int(11) NOT NULL,
  `DeliveryDate` date NOT NULL,
  `PurchasePrice` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `UserID` int(11) NOT NULL,
  `Name` varchar(100) NOT NULL,
  `Email` varchar(100) NOT NULL,
  `Phone` varchar(20) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `Role` enum('Student','Staff','Faculty','Admin') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`UserID`, `Name`, `Email`, `Phone`, `Password`, `Role`) VALUES
(1, 'Farhan Masud', 'farhan@gmail.com', '01711111111', 'hash123', 'Student'),
(2, 'Ayesha Rahman', 'ayesha@gmail.com', '01722222222', 'hash123', 'Student'),
(3, 'Tanvir Ahmed', 'tanvir@gmail.com', '01733333333', '$2y$10$abc123...', 'Staff'),
(4, 'Rafiqul Islam', 'rafiq@gmail.com', '01744444444', 'hash123', 'Staff'),
(5, 'Dr. Aminul', 'aminul@gmail.com', '01755555555', 'hash123', 'Faculty'),
(6, 'Sabbir Hossain', 'sabbir@gmail.com', '01799999999', 'securepass123', 'Student'),
(7, 'Test Student', 'student@gmail.com', '01700000000', '123456', 'Student'),
(8, 'Tanjilur Rahman', 'tanjilur123rahman@gmail.com', '01784372317', '$2y$10$RlKbIGIfWOKA2Iy6xsyi3ej2gr.LUrDKkKZW3bxysQmRnHbmReO/a', 'Student'),
(9, 'Test Staff', 'teststaff@gmail.com', '01812345678', '$2y$10$DnEozcir.kErZ29PdE30F.3JLGpbFMA3D4O3ggxyxxSI8I9dB9iES', 'Staff'),
(10, 'System Admin', 'admin@gmail.com', '01812345679', '$2y$10$DnEozcir.kErZ29PdE30F.3JLGpbFMA3D4O3ggxyxxSI8I9dB9iES', 'Admin');

-- --------------------------------------------------------

--
-- Table structure for table `wallet_transaction`
--

CREATE TABLE `wallet_transaction` (
  `TransactionID` int(11) NOT NULL,
  `StudentID` int(11) NOT NULL,
  `Amount` decimal(10,2) NOT NULL,
  `TransactionType` enum('Deposit','Payment','Refund') NOT NULL,
  `TransactionDate` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `waste`
--

CREATE TABLE `waste` (
  `WasteID` int(11) NOT NULL,
  `FoodID` int(11) NOT NULL,
  `WasteDate` date NOT NULL,
  `Quantity` int(11) NOT NULL,
  `Reason` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `counter`
--
ALTER TABLE `counter`
  ADD PRIMARY KEY (`CounterID`);

--
-- Indexes for table `counter_serves_food`
--
ALTER TABLE `counter_serves_food`
  ADD PRIMARY KEY (`CounterID`,`FoodID`),
  ADD KEY `FoodID` (`FoodID`);

--
-- Indexes for table `food`
--
ALTER TABLE `food`
  ADD PRIMARY KEY (`FoodID`);

--
-- Indexes for table `food_review`
--
ALTER TABLE `food_review`
  ADD PRIMARY KEY (`ReviewID`),
  ADD KEY `StudentID` (`StudentID`),
  ADD KEY `FoodID` (`FoodID`);

--
-- Indexes for table `ingredient`
--
ALTER TABLE `ingredient`
  ADD PRIMARY KEY (`IngredientID`);

--
-- Indexes for table `prep_room`
--
ALTER TABLE `prep_room`
  ADD PRIMARY KEY (`PrepID`),
  ADD KEY `FoodID` (`FoodID`);

--
-- Indexes for table `prep_room_uses_ingredient`
--
ALTER TABLE `prep_room_uses_ingredient`
  ADD PRIMARY KEY (`PrepID`,`IngredientID`),
  ADD KEY `IngredientID` (`IngredientID`);

--
-- Indexes for table `sale`
--
ALTER TABLE `sale`
  ADD PRIMARY KEY (`SaleID`),
  ADD KEY `StudentID` (`StudentID`),
  ADD KEY `PickupCounterID` (`PickupCounterID`);

--
-- Indexes for table `sale_item`
--
ALTER TABLE `sale_item`
  ADD PRIMARY KEY (`SaleID`,`ItemNo`),
  ADD KEY `FoodID` (`FoodID`);

--
-- Indexes for table `seat_reservation`
--
ALTER TABLE `seat_reservation`
  ADD PRIMARY KEY (`ReservationID`),
  ADD KEY `StudentID` (`StudentID`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`UserID`),
  ADD KEY `CounterID` (`CounterID`);

--
-- Indexes for table `staff_attendance`
--
ALTER TABLE `staff_attendance`
  ADD PRIMARY KEY (`AttendanceID`),
  ADD KEY `StaffID` (`StaffID`);

--
-- Indexes for table `staff_review`
--
ALTER TABLE `staff_review`
  ADD PRIMARY KEY (`ReviewID`),
  ADD KEY `StudentID` (`StudentID`),
  ADD KEY `StaffID` (`StaffID`);

--
-- Indexes for table `staff_task`
--
ALTER TABLE `staff_task`
  ADD PRIMARY KEY (`TaskID`),
  ADD KEY `StaffID` (`StaffID`);

--
-- Indexes for table `student`
--
ALTER TABLE `student`
  ADD PRIMARY KEY (`UserID`);

--
-- Indexes for table `supplier`
--
ALTER TABLE `supplier`
  ADD PRIMARY KEY (`SupplierID`);

--
-- Indexes for table `supplier_supplies_ingredient`
--
ALTER TABLE `supplier_supplies_ingredient`
  ADD PRIMARY KEY (`SupplierID`,`IngredientID`,`DeliveryDate`),
  ADD KEY `IngredientID` (`IngredientID`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`UserID`),
  ADD UNIQUE KEY `Email` (`Email`);

--
-- Indexes for table `wallet_transaction`
--
ALTER TABLE `wallet_transaction`
  ADD PRIMARY KEY (`TransactionID`),
  ADD KEY `StudentID` (`StudentID`);

--
-- Indexes for table `waste`
--
ALTER TABLE `waste`
  ADD PRIMARY KEY (`WasteID`),
  ADD KEY `FoodID` (`FoodID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `counter`
--
ALTER TABLE `counter`
  MODIFY `CounterID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `food`
--
ALTER TABLE `food`
  MODIFY `FoodID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `food_review`
--
ALTER TABLE `food_review`
  MODIFY `ReviewID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ingredient`
--
ALTER TABLE `ingredient`
  MODIFY `IngredientID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `prep_room`
--
ALTER TABLE `prep_room`
  MODIFY `PrepID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sale`
--
ALTER TABLE `sale`
  MODIFY `SaleID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `seat_reservation`
--
ALTER TABLE `seat_reservation`
  MODIFY `ReservationID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_attendance`
--
ALTER TABLE `staff_attendance`
  MODIFY `AttendanceID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_review`
--
ALTER TABLE `staff_review`
  MODIFY `ReviewID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_task`
--
ALTER TABLE `staff_task`
  MODIFY `TaskID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `supplier`
--
ALTER TABLE `supplier`
  MODIFY `SupplierID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `UserID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `wallet_transaction`
--
ALTER TABLE `wallet_transaction`
  MODIFY `TransactionID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `waste`
--
ALTER TABLE `waste`
  MODIFY `WasteID` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `counter_serves_food`
--
ALTER TABLE `counter_serves_food`
  ADD CONSTRAINT `counter_serves_food_ibfk_1` FOREIGN KEY (`CounterID`) REFERENCES `counter` (`CounterID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `counter_serves_food_ibfk_2` FOREIGN KEY (`FoodID`) REFERENCES `food` (`FoodID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `food_review`
--
ALTER TABLE `food_review`
  ADD CONSTRAINT `food_review_ibfk_1` FOREIGN KEY (`StudentID`) REFERENCES `student` (`UserID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `food_review_ibfk_2` FOREIGN KEY (`FoodID`) REFERENCES `food` (`FoodID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `prep_room`
--
ALTER TABLE `prep_room`
  ADD CONSTRAINT `prep_room_ibfk_1` FOREIGN KEY (`FoodID`) REFERENCES `food` (`FoodID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `prep_room_uses_ingredient`
--
ALTER TABLE `prep_room_uses_ingredient`
  ADD CONSTRAINT `prep_room_uses_ingredient_ibfk_1` FOREIGN KEY (`PrepID`) REFERENCES `prep_room` (`PrepID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `prep_room_uses_ingredient_ibfk_2` FOREIGN KEY (`IngredientID`) REFERENCES `ingredient` (`IngredientID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `sale`
--
ALTER TABLE `sale`
  ADD CONSTRAINT `sale_ibfk_1` FOREIGN KEY (`StudentID`) REFERENCES `student` (`UserID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `sale_ibfk_2` FOREIGN KEY (`PickupCounterID`) REFERENCES `counter` (`CounterID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `sale_item`
--
ALTER TABLE `sale_item`
  ADD CONSTRAINT `sale_item_ibfk_1` FOREIGN KEY (`SaleID`) REFERENCES `sale` (`SaleID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `sale_item_ibfk_2` FOREIGN KEY (`FoodID`) REFERENCES `food` (`FoodID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `seat_reservation`
--
ALTER TABLE `seat_reservation`
  ADD CONSTRAINT `seat_reservation_ibfk_1` FOREIGN KEY (`StudentID`) REFERENCES `student` (`UserID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `user` (`UserID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `staff_ibfk_2` FOREIGN KEY (`CounterID`) REFERENCES `counter` (`CounterID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `staff_attendance`
--
ALTER TABLE `staff_attendance`
  ADD CONSTRAINT `staff_attendance_ibfk_1` FOREIGN KEY (`StaffID`) REFERENCES `staff` (`UserID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `staff_review`
--
ALTER TABLE `staff_review`
  ADD CONSTRAINT `staff_review_ibfk_1` FOREIGN KEY (`StudentID`) REFERENCES `student` (`UserID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `staff_review_ibfk_2` FOREIGN KEY (`StaffID`) REFERENCES `staff` (`UserID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `staff_task`
--
ALTER TABLE `staff_task`
  ADD CONSTRAINT `staff_task_ibfk_1` FOREIGN KEY (`StaffID`) REFERENCES `staff` (`UserID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `student`
--
ALTER TABLE `student`
  ADD CONSTRAINT `student_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `user` (`UserID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `supplier_supplies_ingredient`
--
ALTER TABLE `supplier_supplies_ingredient`
  ADD CONSTRAINT `supplier_supplies_ingredient_ibfk_1` FOREIGN KEY (`SupplierID`) REFERENCES `supplier` (`SupplierID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `supplier_supplies_ingredient_ibfk_2` FOREIGN KEY (`IngredientID`) REFERENCES `ingredient` (`IngredientID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `wallet_transaction`
--
ALTER TABLE `wallet_transaction`
  ADD CONSTRAINT `wallet_transaction_ibfk_1` FOREIGN KEY (`StudentID`) REFERENCES `student` (`UserID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `waste`
--
ALTER TABLE `waste`
  ADD CONSTRAINT `waste_ibfk_1` FOREIGN KEY (`FoodID`) REFERENCES `food` (`FoodID`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
