/*
Navicat MySQL Data Transfer

Source Server         : localhos
Source Server Version : 50505
Source Host           : localhost:3306
Source Database       : web_service

Target Server Type    : MYSQL
Target Server Version : 50505
File Encoding         : 65001

Date: 2016-10-14 15:39:04
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for `p_config`
-- ----------------------------
DROP TABLE IF EXISTS `p_config`;
CREATE TABLE `p_config` (
  `name` varchar(40) NOT NULL DEFAULT '',
  `values` varchar(1000) NOT NULL DEFAULT '',
  `remark` varchar(20) NOT NULL DEFAULT '',
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of p_config
-- ----------------------------
INSERT INTO `p_config` VALUES ('access_token', 'r8RxnPAQaypZTmGXj0xoJfpijQ_zvWg62weSMm9UIcThjRetyXuwI3If12ks4NNQ2thRWqiNKzUflTPifHKBtlpFqcT-PsglpK3l004Q8ycEXBfAEAMFJ', '微信的access_token');
INSERT INTO `p_config` VALUES ('jsapi_ticket', 'sM4AOVdWfPE4DxkXGEs8VPI1tU0Gm203wCdxWycEjTBmcGIMJZMHnHjz9L1gjfpho43bea5qxlSdS1SrqmcTRQ', '微信js的ticket');
INSERT INTO `p_config` VALUES ('project_name', '示例项目2', '项目名称');
INSERT INTO `p_config` VALUES ('ticket_expire_time', '1455851141', 'ticket过期时间');
INSERT INTO `p_config` VALUES ('token_expire_time', '1449637515', 'token过期时间');
INSERT INTO `p_config` VALUES ('wx_lock_flag', '0', '是否正在获取新的ticket');

-- ----------------------------
-- Table structure for `p_superadmin`
-- ----------------------------
DROP TABLE IF EXISTS `p_superadmin`;
CREATE TABLE `p_superadmin` (
  `uid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(25) NOT NULL DEFAULT '',
  `password` char(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  `authority` int(1) NOT NULL DEFAULT '0' COMMENT '0：超级管理员',
  PRIMARY KEY (`uid`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of p_superadmin
-- ----------------------------
INSERT INTO `p_superadmin` VALUES ('1', 'admin', '$2y$10$MHL8/8876LMmd29tuxmxs.HIHJAiR14THqFGg3Tt8hGj2Ledl85Ny', '0');

-- ----------------------------
-- Table structure for `p_userbase`
-- ----------------------------
DROP TABLE IF EXISTS `p_userbase`;
CREATE TABLE `p_userbase` (
  `uid` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '用户id',
  `token` char(32) CHARACTER SET utf8mb4 NOT NULL DEFAULT '' COMMENT '用户token',
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of p_userbase
-- ----------------------------
INSERT INTO `p_userbase` VALUES ('1', '0dc87c50ec1c5f6fffde74a7441a725d');
INSERT INTO `p_userbase` VALUES ('2', '78a0c62b0eedbeb4abf5850fa44d1c61');
INSERT INTO `p_userbase` VALUES ('96500488203141257', '9a922ac828c3d3ec29ef022310da9555');

-- ----------------------------
-- Table structure for `p_userdetail`
-- ----------------------------
DROP TABLE IF EXISTS `p_userdetail`;
CREATE TABLE `p_userdetail` (
  `uid` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '用户id',
  `phone` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '手机号',
  `gender` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '性别，0：未填，1：男，2：女',
  `birthday` date NOT NULL DEFAULT '0000-00-00' COMMENT '生日',
  `constellation` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '星座编码',
  `province` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '常住地-省份',
  `city` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '常住地-城市',
  `district` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '常住地-区',
  `sign` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '心情',
  `avatar` varchar(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '' COMMENT '头像',
  `personal_bg` varchar(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '' COMMENT '个人主页背景',
  `album` varchar(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '' COMMENT '个人相册图片地址，以逗号分隔，最多8张',
  `register_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '注册时间',
  PRIMARY KEY (`uid`),
  UNIQUE KEY `phone` (`phone`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of p_userdetail
-- ----------------------------
INSERT INTO `p_userdetail` VALUES ('1', '15968882677', '1', '1989-03-02', '12', '33', '1', '2', '哈哈', '', '', '', '0');
INSERT INTO `p_userdetail` VALUES ('2', '13838173597', '1', '2016-01-21', '11', '12', '2', '0', '', '', '', '', '1453689108');
INSERT INTO `p_userdetail` VALUES ('96500488203141257', '13238173597', '1', '2016-03-02', '12', '0', '0', '0', '', '', '', '', '1456920957');
