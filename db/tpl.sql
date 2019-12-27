/*
Navicat MySQL Data Transfer

Source Server         : localhost_3306
Source Server Version : 50553
Source Host           : localhost:3306
Source Database       : tpl

Target Server Type    : MYSQL
Target Server Version : 50553
File Encoding         : 65001

Date: 2018-10-15 13:46:52
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for `about_us`
-- ----------------------------
DROP TABLE IF EXISTS `about_us`;
CREATE TABLE `about_us` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `content` varchar(255) NOT NULL COMMENT '关于我们内容',
  `mobile` varchar(20) NOT NULL COMMENT '客服电话',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of about_us
-- ----------------------------
INSERT INTO `about_us` VALUES ('1', '关于我们关于我们关于我们关于我们关于我们关于我们关于我们关于我们关于我们关于我们', '13588888888');

-- ----------------------------
-- Table structure for `admin`
-- ----------------------------
DROP TABLE IF EXISTS `admin`;
CREATE TABLE `admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nickname` varchar(20) DEFAULT NULL COMMENT '昵称',
  `name` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `thumb` int(11) NOT NULL DEFAULT '1' COMMENT '管理员头像',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  `login_time` int(11) DEFAULT NULL COMMENT '最后登录时间',
  `login_ip` varchar(100) DEFAULT NULL COMMENT '最后登录ip',
  `admin_cate_id` int(2) NOT NULL DEFAULT '1' COMMENT '管理员分组',
  PRIMARY KEY (`id`),
  KEY `id` (`id`) USING BTREE,
  KEY `admin_cate_id` (`admin_cate_id`) USING BTREE,
  KEY `nickname` (`nickname`) USING BTREE,
  KEY `create_time` (`create_time`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=18 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of admin
-- ----------------------------
INSERT INTO `admin` VALUES ('1', '超管大大', 'admin', '9eb2b9ad495a75f80f9cf67ed08bbaae', '15', '1510885948', '1537860415', '1539566851', '', '1');
INSERT INTO `admin` VALUES ('16', '龙先生', 'long123', '9eb2b9ad495a75f80f9cf67ed08bbaae', '2', '1531712323', '1531712323', '1531983762', 'XX内网IP,127.0.0.1', '20');
INSERT INTO `admin` VALUES ('17', '高新区店长-刘德华', '15881050779', '9eb2b9ad495a75f80f9cf67ed08bbaae', '5', '1532662753', '1532662753', '1537860337', 'XX内网IP,127.0.0.1', '21');

-- ----------------------------
-- Table structure for `admin_cate`
-- ----------------------------
DROP TABLE IF EXISTS `admin_cate`;
CREATE TABLE `admin_cate` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `permissions` text COMMENT '权限菜单',
  `create_time` int(11) NOT NULL,
  `update_time` int(11) NOT NULL,
  `desc` text COMMENT '备注',
  PRIMARY KEY (`id`),
  KEY `id` (`id`) USING BTREE,
  KEY `name` (`name`) USING BTREE,
  KEY `create_time` (`create_time`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=22 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of admin_cate
-- ----------------------------
INSERT INTO `admin_cate` VALUES ('1', '超级管理员', '', '0', '1531985420', '超级管理员，拥有最高权限！');
INSERT INTO `admin_cate` VALUES ('20', '内容管理员', '1,6,7,8,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48', '1531707600', '1537860317', '无');
INSERT INTO `admin_cate` VALUES ('21', '门店管理员', '52,53,54,55,56,60', '1532662600', '1537860304', '');

-- ----------------------------
-- Table structure for `admin_log`
-- ----------------------------
DROP TABLE IF EXISTS `admin_log`;
CREATE TABLE `admin_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_menu_id` int(11) NOT NULL COMMENT '操作菜单id',
  `admin_id` int(11) NOT NULL COMMENT '操作者id',
  `ip` varchar(100) DEFAULT NULL COMMENT '操作ip',
  `operation_id` varchar(200) DEFAULT NULL COMMENT '操作关联id',
  `create_time` int(11) NOT NULL COMMENT '操作时间',
  PRIMARY KEY (`id`),
  KEY `id` (`id`) USING BTREE,
  KEY `admin_id` (`admin_id`) USING BTREE,
  KEY `create_time` (`create_time`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=348 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of admin_log
-- ----------------------------
INSERT INTO `admin_log` VALUES ('1', '50', '1', '127.0.0.1', '', '1531381978');
INSERT INTO `admin_log` VALUES ('2', '50', '1', '127.0.0.1', '', '1531469527');
INSERT INTO `admin_log` VALUES ('3', '50', '1', '127.0.0.1', '', '1531469876');
INSERT INTO `admin_log` VALUES ('4', '50', '1', '127.0.0.1', '', '1531704975');
INSERT INTO `admin_log` VALUES ('5', '50', '1', '127.0.0.1', '', '1531706311');
INSERT INTO `admin_log` VALUES ('6', '28', '1', '127.0.0.1', '20', '1531707601');
INSERT INTO `admin_log` VALUES ('7', '49', '1', '127.0.0.1', '2', '1531712301');
INSERT INTO `admin_log` VALUES ('8', '25', '1', '127.0.0.1', '16', '1531712323');
INSERT INTO `admin_log` VALUES ('9', '50', '16', '127.0.0.1', '', '1531712348');
INSERT INTO `admin_log` VALUES ('10', '50', '1', '127.0.0.1', '', '1531712491');
INSERT INTO `admin_log` VALUES ('11', '8', '1', '127.0.0.1', '', '1531712504');
INSERT INTO `admin_log` VALUES ('12', '50', '1', '127.0.0.1', '', '1531965284');
INSERT INTO `admin_log` VALUES ('13', '4', '1', '127.0.0.1', '31', '1531982914');
INSERT INTO `admin_log` VALUES ('14', '4', '1', '127.0.0.1', '32', '1531982956');
INSERT INTO `admin_log` VALUES ('15', '28', '1', '127.0.0.1', '1', '1531982989');
INSERT INTO `admin_log` VALUES ('16', '4', '1', '127.0.0.1', '1', '1531983029');
INSERT INTO `admin_log` VALUES ('17', '4', '1', '127.0.0.1', '2', '1531983036');
INSERT INTO `admin_log` VALUES ('18', '4', '1', '127.0.0.1', '3', '1531983045');
INSERT INTO `admin_log` VALUES ('19', '28', '1', '127.0.0.1', '1', '1531983065');
INSERT INTO `admin_log` VALUES ('20', '4', '1', '127.0.0.1', '9', '1531983081');
INSERT INTO `admin_log` VALUES ('21', '4', '1', '127.0.0.1', '10', '1531983135');
INSERT INTO `admin_log` VALUES ('22', '28', '1', '127.0.0.1', '20', '1531983728');
INSERT INTO `admin_log` VALUES ('23', '50', '16', '127.0.0.1', '', '1531983762');
INSERT INTO `admin_log` VALUES ('24', '42', '16', '127.0.0.1', '2', '1531983793');
INSERT INTO `admin_log` VALUES ('25', '50', '1', '127.0.0.1', '', '1531983876');
INSERT INTO `admin_log` VALUES ('26', '4', '1', '127.0.0.1', '52', '1531984384');
INSERT INTO `admin_log` VALUES ('27', '28', '1', '127.0.0.1', '1', '1531984407');
INSERT INTO `admin_log` VALUES ('28', '4', '1', '127.0.0.1', '53', '1531984998');
INSERT INTO `admin_log` VALUES ('29', '28', '1', '127.0.0.1', '1', '1531985420');
INSERT INTO `admin_log` VALUES ('30', '4', '1', '127.0.0.1', '1', '1531988360');
INSERT INTO `admin_log` VALUES ('31', '4', '1', '127.0.0.1', '54', '1531989478');
INSERT INTO `admin_log` VALUES ('32', '4', '1', '127.0.0.1', '55', '1531989627');
INSERT INTO `admin_log` VALUES ('33', '4', '1', '127.0.0.1', '56', '1531989667');
INSERT INTO `admin_log` VALUES ('34', '34', '1', '127.0.0.1', '1', '1531990457');
INSERT INTO `admin_log` VALUES ('35', '34', '1', '127.0.0.1', '2', '1531990493');
INSERT INTO `admin_log` VALUES ('36', '34', '1', '127.0.0.1', '1', '1531990515');
INSERT INTO `admin_log` VALUES ('37', '34', '1', '127.0.0.1', '2', '1531990527');
INSERT INTO `admin_log` VALUES ('38', '34', '1', '127.0.0.1', '3', '1531990542');
INSERT INTO `admin_log` VALUES ('39', '34', '1', '127.0.0.1', '4', '1531990565');
INSERT INTO `admin_log` VALUES ('40', '4', '1', '127.0.0.1', '57', '1531990907');
INSERT INTO `admin_log` VALUES ('41', '4', '1', '127.0.0.1', '58', '1531991098');
INSERT INTO `admin_log` VALUES ('42', '4', '1', '127.0.0.1', '59', '1531991129');
INSERT INTO `admin_log` VALUES ('43', '4', '1', '127.0.0.1', '59', '1531991157');
INSERT INTO `admin_log` VALUES ('44', '4', '1', '127.0.0.1', '58', '1531991202');
INSERT INTO `admin_log` VALUES ('45', '4', '1', '127.0.0.1', '59', '1531991217');
INSERT INTO `admin_log` VALUES ('46', '50', '1', '127.0.0.1', '', '1532070414');
INSERT INTO `admin_log` VALUES ('47', '49', '1', '127.0.0.1', '3', '1532070537');
INSERT INTO `admin_log` VALUES ('48', '7', '1', '127.0.0.1', '1', '1532070539');
INSERT INTO `admin_log` VALUES ('49', '49', '1', '127.0.0.1', '4', '1532070638');
INSERT INTO `admin_log` VALUES ('50', '7', '1', '127.0.0.1', '1', '1532070639');
INSERT INTO `admin_log` VALUES ('51', '7', '1', '127.0.0.1', '1', '1532070641');
INSERT INTO `admin_log` VALUES ('52', '7', '1', '127.0.0.1', '1', '1532070643');
INSERT INTO `admin_log` VALUES ('53', '7', '1', '127.0.0.1', '1', '1532070647');
INSERT INTO `admin_log` VALUES ('54', '7', '1', '127.0.0.1', '1', '1532070648');
INSERT INTO `admin_log` VALUES ('55', '7', '1', '127.0.0.1', '1', '1532070689');
INSERT INTO `admin_log` VALUES ('56', '42', '1', '127.0.0.1', '5', '1532075169');
INSERT INTO `admin_log` VALUES ('57', '45', '1', '127.0.0.1', '5', '1532075176');
INSERT INTO `admin_log` VALUES ('58', '45', '1', '127.0.0.1', '6', '1532075192');
INSERT INTO `admin_log` VALUES ('59', '50', '1', '127.0.0.1', '', '1532484206');
INSERT INTO `admin_log` VALUES ('60', '45', '1', '127.0.0.1', '7', '1532490388');
INSERT INTO `admin_log` VALUES ('61', '50', '1', '127.0.0.1', '', '1532662462');
INSERT INTO `admin_log` VALUES ('62', '4', '1', '127.0.0.1', '60', '1532662554');
INSERT INTO `admin_log` VALUES ('63', '28', '1', '127.0.0.1', '21', '1532662600');
INSERT INTO `admin_log` VALUES ('64', '49', '1', '127.0.0.1', '5', '1532662657');
INSERT INTO `admin_log` VALUES ('65', '25', '1', '127.0.0.1', '17', '1532662753');
INSERT INTO `admin_log` VALUES ('66', '50', '17', '127.0.0.1', '', '1532662785');
INSERT INTO `admin_log` VALUES ('67', '50', '1', '127.0.0.1', '', '1532662854');
INSERT INTO `admin_log` VALUES ('68', '50', '1', '127.0.0.1', '', '1533210879');
INSERT INTO `admin_log` VALUES ('69', '50', '1', '127.0.0.1', '', '1533872819');
INSERT INTO `admin_log` VALUES ('70', '50', '1', '127.0.0.1', '', '1537426133');
INSERT INTO `admin_log` VALUES ('71', '50', '1', '127.0.0.1', '', '1537495505');
INSERT INTO `admin_log` VALUES ('72', '50', '1', '127.0.0.1', '', '1537495708');
INSERT INTO `admin_log` VALUES ('73', '50', '1', '127.0.0.1', '', '1537839572');
INSERT INTO `admin_log` VALUES ('74', '34', '1', '127.0.0.1', '5', '1537856762');
INSERT INTO `admin_log` VALUES ('75', '49', '1', '127.0.0.1', '6', '1537856791');
INSERT INTO `admin_log` VALUES ('76', '37', '1', '127.0.0.1', '1', '1537856793');
INSERT INTO `admin_log` VALUES ('77', '39', '1', '127.0.0.1', '1', '1537856798');
INSERT INTO `admin_log` VALUES ('78', '39', '1', '127.0.0.1', '1', '1537856799');
INSERT INTO `admin_log` VALUES ('79', '38', '1', '127.0.0.1', '1', '1537856799');
INSERT INTO `admin_log` VALUES ('80', '38', '1', '127.0.0.1', '1', '1537856800');
INSERT INTO `admin_log` VALUES ('81', '37', '1', '127.0.0.1', '1', '1537856841');
INSERT INTO `admin_log` VALUES ('82', '49', '1', '127.0.0.1', '7', '1537858998');
INSERT INTO `admin_log` VALUES ('83', '49', '1', '127.0.0.1', '8', '1537859065');
INSERT INTO `admin_log` VALUES ('84', '49', '1', '127.0.0.1', '9', '1537859180');
INSERT INTO `admin_log` VALUES ('85', '49', '1', '127.0.0.1', '10', '1537859415');
INSERT INTO `admin_log` VALUES ('86', '49', '1', '127.0.0.1', '11', '1537859472');
INSERT INTO `admin_log` VALUES ('87', '49', '1', '127.0.0.1', '12', '1537859490');
INSERT INTO `admin_log` VALUES ('88', '49', '1', '127.0.0.1', '13', '1537859590');
INSERT INTO `admin_log` VALUES ('89', '4', '1', '127.0.0.1', '61', '1537860119');
INSERT INTO `admin_log` VALUES ('90', '49', '1', '127.0.0.1', '14', '1537860142');
INSERT INTO `admin_log` VALUES ('91', '61', '1', '127.0.0.1', '10014', '1537860144');
INSERT INTO `admin_log` VALUES ('92', '28', '1', '127.0.0.1', '20', '1537860199');
INSERT INTO `admin_log` VALUES ('93', '50', '17', '127.0.0.1', '', '1537860236');
INSERT INTO `admin_log` VALUES ('94', '50', '1', '127.0.0.1', '', '1537860257');
INSERT INTO `admin_log` VALUES ('95', '28', '1', '127.0.0.1', '21', '1537860304');
INSERT INTO `admin_log` VALUES ('96', '28', '1', '127.0.0.1', '20', '1537860317');
INSERT INTO `admin_log` VALUES ('97', '50', '17', '127.0.0.1', '', '1537860337');
INSERT INTO `admin_log` VALUES ('98', '50', '1', '127.0.0.1', '', '1537860378');
INSERT INTO `admin_log` VALUES ('99', '49', '1', '127.0.0.1', '15', '1537860398');
INSERT INTO `admin_log` VALUES ('100', '25', '1', '127.0.0.1', '1', '1537860415');
INSERT INTO `admin_log` VALUES ('101', '39', '1', '127.0.0.1', '1', '1537860545');
INSERT INTO `admin_log` VALUES ('102', '39', '1', '127.0.0.1', '1', '1537860546');
INSERT INTO `admin_log` VALUES ('103', '38', '1', '127.0.0.1', '1', '1537860547');
INSERT INTO `admin_log` VALUES ('104', '38', '1', '127.0.0.1', '1', '1537860547');
INSERT INTO `admin_log` VALUES ('105', '38', '1', '127.0.0.1', '1', '1537860548');
INSERT INTO `admin_log` VALUES ('106', '38', '1', '127.0.0.1', '1', '1537860549');
INSERT INTO `admin_log` VALUES ('107', '38', '1', '127.0.0.1', '1', '1537860549');
INSERT INTO `admin_log` VALUES ('108', '38', '1', '127.0.0.1', '1', '1537860550');
INSERT INTO `admin_log` VALUES ('109', '38', '1', '127.0.0.1', '1', '1537860550');
INSERT INTO `admin_log` VALUES ('110', '38', '1', '127.0.0.1', '1', '1537860551');
INSERT INTO `admin_log` VALUES ('111', '38', '1', '127.0.0.1', '1', '1537860552');
INSERT INTO `admin_log` VALUES ('112', '38', '1', '127.0.0.1', '1', '1537860554');
INSERT INTO `admin_log` VALUES ('113', '38', '1', '127.0.0.1', '1', '1537860556');
INSERT INTO `admin_log` VALUES ('114', '38', '1', '127.0.0.1', '1', '1537860557');
INSERT INTO `admin_log` VALUES ('115', '38', '1', '127.0.0.1', '1', '1537860561');
INSERT INTO `admin_log` VALUES ('116', '38', '1', '127.0.0.1', '1', '1537860563');
INSERT INTO `admin_log` VALUES ('117', '38', '1', '127.0.0.1', '1', '1537860565');
INSERT INTO `admin_log` VALUES ('118', '38', '1', '127.0.0.1', '1', '1537860565');
INSERT INTO `admin_log` VALUES ('119', '38', '1', '127.0.0.1', '1', '1537860566');
INSERT INTO `admin_log` VALUES ('120', '38', '1', '127.0.0.1', '1', '1537860567');
INSERT INTO `admin_log` VALUES ('121', '38', '1', '127.0.0.1', '1', '1537860568');
INSERT INTO `admin_log` VALUES ('122', '38', '1', '127.0.0.1', '1', '1537860568');
INSERT INTO `admin_log` VALUES ('123', '38', '1', '127.0.0.1', '1', '1537860569');
INSERT INTO `admin_log` VALUES ('124', '38', '1', '127.0.0.1', '1', '1537860570');
INSERT INTO `admin_log` VALUES ('125', '20', '1', '127.0.0.1', '1', '1537861118');
INSERT INTO `admin_log` VALUES ('126', '20', '1', '127.0.0.1', '1', '1537861123');
INSERT INTO `admin_log` VALUES ('127', '4', '1', '127.0.0.1', '62', '1537862646');
INSERT INTO `admin_log` VALUES ('128', '62', '1', '127.0.0.1', '10014', '1537862654');
INSERT INTO `admin_log` VALUES ('129', '62', '1', '127.0.0.1', '10013', '1537862659');
INSERT INTO `admin_log` VALUES ('130', '4', '1', '127.0.0.1', '55', '1537863383');
INSERT INTO `admin_log` VALUES ('131', '4', '1', '127.0.0.1', '63', '1537863683');
INSERT INTO `admin_log` VALUES ('132', '4', '1', '127.0.0.1', '64', '1537863729');
INSERT INTO `admin_log` VALUES ('133', '64', '1', '127.0.0.1', '10000', '1537863736');
INSERT INTO `admin_log` VALUES ('134', '4', '1', '127.0.0.1', '65', '1537863948');
INSERT INTO `admin_log` VALUES ('135', '4', '1', '127.0.0.1', '66', '1537864849');
INSERT INTO `admin_log` VALUES ('136', '66', '1', '127.0.0.1', '1', '1537864896');
INSERT INTO `admin_log` VALUES ('137', '66', '1', '127.0.0.1', '1', '1537864900');
INSERT INTO `admin_log` VALUES ('138', '4', '1', '127.0.0.1', '67', '1537865450');
INSERT INTO `admin_log` VALUES ('139', '4', '1', '127.0.0.1', '68', '1537865514');
INSERT INTO `admin_log` VALUES ('140', '4', '1', '127.0.0.1', '69', '1537866980');
INSERT INTO `admin_log` VALUES ('141', '39', '1', '127.0.0.1', '1', '1537866992');
INSERT INTO `admin_log` VALUES ('142', '69', '1', '127.0.0.1', '1', '1537866999');
INSERT INTO `admin_log` VALUES ('143', '69', '1', '127.0.0.1', '1', '1537866999');
INSERT INTO `admin_log` VALUES ('144', '69', '1', '127.0.0.1', '1', '1537867000');
INSERT INTO `admin_log` VALUES ('145', '69', '1', '127.0.0.1', '2', '1537867002');
INSERT INTO `admin_log` VALUES ('146', '69', '1', '127.0.0.1', '3', '1537867003');
INSERT INTO `admin_log` VALUES ('147', '69', '1', '127.0.0.1', '4', '1537867004');
INSERT INTO `admin_log` VALUES ('148', '69', '1', '127.0.0.1', '5', '1537867004');
INSERT INTO `admin_log` VALUES ('149', '69', '1', '127.0.0.1', '5', '1537867015');
INSERT INTO `admin_log` VALUES ('150', '69', '1', '127.0.0.1', '4', '1537867015');
INSERT INTO `admin_log` VALUES ('151', '69', '1', '127.0.0.1', '3', '1537867016');
INSERT INTO `admin_log` VALUES ('152', '69', '1', '127.0.0.1', '2', '1537867016');
INSERT INTO `admin_log` VALUES ('153', '69', '1', '127.0.0.1', '11', '1537867017');
INSERT INTO `admin_log` VALUES ('154', '69', '1', '127.0.0.1', '10', '1537867018');
INSERT INTO `admin_log` VALUES ('155', '69', '1', '127.0.0.1', '9', '1537867018');
INSERT INTO `admin_log` VALUES ('156', '69', '1', '127.0.0.1', '8', '1537867019');
INSERT INTO `admin_log` VALUES ('157', '69', '1', '127.0.0.1', '7', '1537867020');
INSERT INTO `admin_log` VALUES ('158', '69', '1', '127.0.0.1', '7', '1537867021');
INSERT INTO `admin_log` VALUES ('159', '69', '1', '127.0.0.1', '8', '1537867021');
INSERT INTO `admin_log` VALUES ('160', '69', '1', '127.0.0.1', '9', '1537867022');
INSERT INTO `admin_log` VALUES ('161', '69', '1', '127.0.0.1', '10', '1537867023');
INSERT INTO `admin_log` VALUES ('162', '69', '1', '127.0.0.1', '11', '1537867024');
INSERT INTO `admin_log` VALUES ('163', '50', '1', '127.0.0.1', '', '1537925514');
INSERT INTO `admin_log` VALUES ('164', '69', '1', '127.0.0.1', '1', '1537928300');
INSERT INTO `admin_log` VALUES ('165', '69', '1', '127.0.0.1', '1', '1537928301');
INSERT INTO `admin_log` VALUES ('166', '69', '1', '127.0.0.1', '1', '1537928522');
INSERT INTO `admin_log` VALUES ('167', '69', '1', '127.0.0.1', '6', '1537928523');
INSERT INTO `admin_log` VALUES ('168', '69', '1', '127.0.0.1', '6', '1537928524');
INSERT INTO `admin_log` VALUES ('169', '69', '1', '127.0.0.1', '1', '1537928524');
INSERT INTO `admin_log` VALUES ('170', '69', '1', '127.0.0.1', '1', '1537928617');
INSERT INTO `admin_log` VALUES ('171', '69', '1', '127.0.0.1', '1', '1537928618');
INSERT INTO `admin_log` VALUES ('172', '38', '1', '127.0.0.1', '1', '1537928832');
INSERT INTO `admin_log` VALUES ('173', '39', '1', '127.0.0.1', '1', '1537928833');
INSERT INTO `admin_log` VALUES ('174', '38', '1', '127.0.0.1', '1', '1537928834');
INSERT INTO `admin_log` VALUES ('175', '39', '1', '127.0.0.1', '1', '1537928835');
INSERT INTO `admin_log` VALUES ('176', '38', '1', '127.0.0.1', '1', '1537928836');
INSERT INTO `admin_log` VALUES ('177', '69', '1', '127.0.0.1', '7', '1537929017');
INSERT INTO `admin_log` VALUES ('178', '69', '1', '127.0.0.1', '7', '1537929018');
INSERT INTO `admin_log` VALUES ('179', '69', '1', '127.0.0.1', '7', '1537929018');
INSERT INTO `admin_log` VALUES ('180', '69', '1', '127.0.0.1', '7', '1537929019');
INSERT INTO `admin_log` VALUES ('181', '4', '1', '127.0.0.1', '70', '1537929193');
INSERT INTO `admin_log` VALUES ('182', '70', '1', '127.0.0.1', '1', '1537929199');
INSERT INTO `admin_log` VALUES ('183', '70', '1', '127.0.0.1', '1', '1537929200');
INSERT INTO `admin_log` VALUES ('185', '4', '1', '127.0.0.1', '71', '1537929528');
INSERT INTO `admin_log` VALUES ('186', '71', '1', '127.0.0.1', '18', '1537929552');
INSERT INTO `admin_log` VALUES ('187', '71', '1', '127.0.0.1', '19', '1537929562');
INSERT INTO `admin_log` VALUES ('188', '71', '1', '127.0.0.1', '20', '1537929580');
INSERT INTO `admin_log` VALUES ('189', '70', '1', '127.0.0.1', '19', '1537932059');
INSERT INTO `admin_log` VALUES ('190', '70', '1', '127.0.0.1', '19', '1537932060');
INSERT INTO `admin_log` VALUES ('191', '71', '1', '127.0.0.1', '21', '1537932114');
INSERT INTO `admin_log` VALUES ('192', '71', '1', '127.0.0.1', '22', '1537932150');
INSERT INTO `admin_log` VALUES ('193', '71', '1', '127.0.0.1', '23', '1537932270');
INSERT INTO `admin_log` VALUES ('194', '71', '1', '127.0.0.1', '1', '1537932337');
INSERT INTO `admin_log` VALUES ('195', '70', '1', '127.0.0.1', '1', '1537932342');
INSERT INTO `admin_log` VALUES ('196', '71', '1', '127.0.0.1', '1', '1537932853');
INSERT INTO `admin_log` VALUES ('197', '71', '1', '127.0.0.1', '1', '1537932859');
INSERT INTO `admin_log` VALUES ('198', '0', '1', '127.0.0.1', '', '1537950377');
INSERT INTO `admin_log` VALUES ('199', '0', '1', '127.0.0.1', '', '1537950381');
INSERT INTO `admin_log` VALUES ('200', '0', '1', '127.0.0.1', '', '1537950416');
INSERT INTO `admin_log` VALUES ('201', '0', '1', '127.0.0.1', '', '1537950419');
INSERT INTO `admin_log` VALUES ('202', '0', '1', '127.0.0.1', '', '1537950478');
INSERT INTO `admin_log` VALUES ('203', '0', '1', '127.0.0.1', '', '1537950498');
INSERT INTO `admin_log` VALUES ('204', '0', '1', '127.0.0.1', '', '1537950502');
INSERT INTO `admin_log` VALUES ('205', '0', '1', '127.0.0.1', '', '1537950504');
INSERT INTO `admin_log` VALUES ('206', '0', '1', '127.0.0.1', '', '1537950560');
INSERT INTO `admin_log` VALUES ('207', '0', '1', '127.0.0.1', '', '1537950564');
INSERT INTO `admin_log` VALUES ('208', '0', '1', '127.0.0.1', '', '1537950571');
INSERT INTO `admin_log` VALUES ('209', '0', '1', '127.0.0.1', '', '1537950572');
INSERT INTO `admin_log` VALUES ('210', '0', '1', '127.0.0.1', '', '1537950572');
INSERT INTO `admin_log` VALUES ('211', '0', '1', '127.0.0.1', '', '1537950573');
INSERT INTO `admin_log` VALUES ('212', '0', '1', '127.0.0.1', '', '1537950575');
INSERT INTO `admin_log` VALUES ('213', '0', '1', '127.0.0.1', '', '1537950593');
INSERT INTO `admin_log` VALUES ('214', '0', '1', '127.0.0.1', '', '1537950685');
INSERT INTO `admin_log` VALUES ('215', '4', '1', '127.0.0.1', '72', '1537950792');
INSERT INTO `admin_log` VALUES ('216', '72', '1', '127.0.0.1', '', '1537950858');
INSERT INTO `admin_log` VALUES ('217', '71', '1', '127.0.0.1', '1', '1537952483');
INSERT INTO `admin_log` VALUES ('218', '70', '1', '127.0.0.1', '1', '1537952487');
INSERT INTO `admin_log` VALUES ('219', '70', '1', '127.0.0.1', '5', '1537952492');
INSERT INTO `admin_log` VALUES ('220', '70', '1', '127.0.0.1', '3', '1537952492');
INSERT INTO `admin_log` VALUES ('221', '70', '1', '127.0.0.1', '2', '1537952493');
INSERT INTO `admin_log` VALUES ('222', '72', '1', '127.0.0.1', '', '1537952505');
INSERT INTO `admin_log` VALUES ('223', '50', '1', '127.0.0.1', '', '1538013872');
INSERT INTO `admin_log` VALUES ('224', '4', '1', '127.0.0.1', '68', '1538014025');
INSERT INTO `admin_log` VALUES ('225', '4', '1', '127.0.0.1', '73', '1538014141');
INSERT INTO `admin_log` VALUES ('226', '4', '1', '127.0.0.1', '74', '1538014191');
INSERT INTO `admin_log` VALUES ('227', '74', '1', '127.0.0.1', '', '1538014205');
INSERT INTO `admin_log` VALUES ('228', '74', '1', '127.0.0.1', '', '1538014214');
INSERT INTO `admin_log` VALUES ('229', '74', '1', '127.0.0.1', '', '1538014226');
INSERT INTO `admin_log` VALUES ('230', '74', '1', '127.0.0.1', '', '1538014275');
INSERT INTO `admin_log` VALUES ('231', '74', '1', '127.0.0.1', '', '1538014281');
INSERT INTO `admin_log` VALUES ('232', '71', '1', '127.0.0.1', '24', '1538020813');
INSERT INTO `admin_log` VALUES ('233', '50', '1', '127.0.0.1', '', '1538098747');
INSERT INTO `admin_log` VALUES ('234', '74', '1', '127.0.0.1', '', '1538100886');
INSERT INTO `admin_log` VALUES ('235', '74', '1', '127.0.0.1', '', '1538100913');
INSERT INTO `admin_log` VALUES ('236', '4', '1', '127.0.0.1', '73', '1538119630');
INSERT INTO `admin_log` VALUES ('237', '4', '1', '127.0.0.1', '74', '1538119643');
INSERT INTO `admin_log` VALUES ('238', '74', '1', '127.0.0.1', '', '1538119678');
INSERT INTO `admin_log` VALUES ('239', '74', '1', '127.0.0.1', '', '1538119690');
INSERT INTO `admin_log` VALUES ('240', '74', '1', '127.0.0.1', '', '1538119696');
INSERT INTO `admin_log` VALUES ('241', '74', '1', '127.0.0.1', '', '1538119701');
INSERT INTO `admin_log` VALUES ('242', '73', '1', '127.0.0.1', '28', '1538119941');
INSERT INTO `admin_log` VALUES ('243', '50', '1', '127.0.0.1', '', '1538183957');
INSERT INTO `admin_log` VALUES ('244', '50', '1', '127.0.0.1', '', '1538187024');
INSERT INTO `admin_log` VALUES ('245', '73', '1', '127.0.0.1', '29', '1538187263');
INSERT INTO `admin_log` VALUES ('246', '73', '1', '127.0.0.1', '30', '1538187921');
INSERT INTO `admin_log` VALUES ('247', '73', '1', '127.0.0.1', '31', '1538187950');
INSERT INTO `admin_log` VALUES ('248', '73', '1', '127.0.0.1', '32', '1538187984');
INSERT INTO `admin_log` VALUES ('249', '73', '1', '127.0.0.1', '33', '1538188072');
INSERT INTO `admin_log` VALUES ('250', '73', '1', '127.0.0.1', '34', '1538188088');
INSERT INTO `admin_log` VALUES ('251', '73', '1', '127.0.0.1', '35', '1538188120');
INSERT INTO `admin_log` VALUES ('252', '73', '1', '127.0.0.1', '36', '1538188163');
INSERT INTO `admin_log` VALUES ('253', '73', '1', '127.0.0.1', '37', '1538188167');
INSERT INTO `admin_log` VALUES ('254', '73', '1', '127.0.0.1', '38', '1538188170');
INSERT INTO `admin_log` VALUES ('255', '73', '1', '127.0.0.1', '39', '1538188224');
INSERT INTO `admin_log` VALUES ('256', '73', '1', '127.0.0.1', '40', '1538188235');
INSERT INTO `admin_log` VALUES ('257', '73', '1', '127.0.0.1', '41', '1538188242');
INSERT INTO `admin_log` VALUES ('258', '73', '1', '127.0.0.1', '42', '1538188251');
INSERT INTO `admin_log` VALUES ('259', '73', '1', '127.0.0.1', '43', '1538188272');
INSERT INTO `admin_log` VALUES ('260', '73', '1', '127.0.0.1', '44', '1538189436');
INSERT INTO `admin_log` VALUES ('261', '73', '1', '127.0.0.1', '45', '1538189453');
INSERT INTO `admin_log` VALUES ('262', '4', '1', '127.0.0.1', '75', '1538201868');
INSERT INTO `admin_log` VALUES ('263', '4', '1', '127.0.0.1', '76', '1538202259');
INSERT INTO `admin_log` VALUES ('264', '4', '1', '127.0.0.1', '77', '1538202328');
INSERT INTO `admin_log` VALUES ('265', '76', '1', '127.0.0.1', '6', '1538203760');
INSERT INTO `admin_log` VALUES ('266', '76', '1', '127.0.0.1', '7', '1538203770');
INSERT INTO `admin_log` VALUES ('267', '76', '1', '127.0.0.1', '5', '1538203813');
INSERT INTO `admin_log` VALUES ('268', '76', '1', '127.0.0.1', '5', '1538203818');
INSERT INTO `admin_log` VALUES ('269', '76', '1', '127.0.0.1', '8', '1538205302');
INSERT INTO `admin_log` VALUES ('270', '71', '1', '127.0.0.1', '11', '1538206149');
INSERT INTO `admin_log` VALUES ('271', '71', '1', '127.0.0.1', '11', '1538206161');
INSERT INTO `admin_log` VALUES ('272', '71', '1', '127.0.0.1', '24', '1538211991');
INSERT INTO `admin_log` VALUES ('273', '71', '1', '127.0.0.1', '24', '1538212004');
INSERT INTO `admin_log` VALUES ('274', '70', '1', '127.0.0.1', '24', '1538212054');
INSERT INTO `admin_log` VALUES ('275', '4', '1', '127.0.0.1', '672', '1538212883');
INSERT INTO `admin_log` VALUES ('276', '4', '1', '127.0.0.1', '673', '1538212930');
INSERT INTO `admin_log` VALUES ('277', '4', '1', '127.0.0.1', '673', '1538212960');
INSERT INTO `admin_log` VALUES ('278', '4', '1', '127.0.0.1', '674', '1538212992');
INSERT INTO `admin_log` VALUES ('279', '4', '1', '127.0.0.1', '74', '1538213007');
INSERT INTO `admin_log` VALUES ('280', '4', '1', '127.0.0.1', '68', '1538213042');
INSERT INTO `admin_log` VALUES ('281', '4', '1', '127.0.0.1', '675', '1538213155');
INSERT INTO `admin_log` VALUES ('282', '50', '1', '127.0.0.1', '', '1538291996');
INSERT INTO `admin_log` VALUES ('283', '50', '1', '127.0.0.1', '', '1538991977');
INSERT INTO `admin_log` VALUES ('284', '50', '1', '127.0.0.1', '', '1539050113');
INSERT INTO `admin_log` VALUES ('285', '73', '1', '127.0.0.1', '46', '1539051966');
INSERT INTO `admin_log` VALUES ('286', '73', '1', '127.0.0.1', '47', '1539051977');
INSERT INTO `admin_log` VALUES ('287', '73', '1', '127.0.0.1', '48', '1539052660');
INSERT INTO `admin_log` VALUES ('288', '73', '1', '127.0.0.1', '48', '1539052673');
INSERT INTO `admin_log` VALUES ('289', '74', '1', '127.0.0.1', '', '1539052705');
INSERT INTO `admin_log` VALUES ('290', '4', '1', '127.0.0.1', '675', '1539056525');
INSERT INTO `admin_log` VALUES ('291', '73', '1', '127.0.0.1', '49', '1539070736');
INSERT INTO `admin_log` VALUES ('292', '73', '1', '127.0.0.1', '1', '1539070783');
INSERT INTO `admin_log` VALUES ('293', '73', '1', '127.0.0.1', '1', '1539070791');
INSERT INTO `admin_log` VALUES ('294', '73', '1', '127.0.0.1', '1', '1539070802');
INSERT INTO `admin_log` VALUES ('295', '73', '1', '127.0.0.1', '1', '1539070811');
INSERT INTO `admin_log` VALUES ('296', '73', '1', '127.0.0.1', '50', '1539070821');
INSERT INTO `admin_log` VALUES ('297', '673', '1', '127.0.0.1', '51', '1539078829');
INSERT INTO `admin_log` VALUES ('298', '50', '1', '127.0.0.1', '', '1539134538');
INSERT INTO `admin_log` VALUES ('299', '62', '1', '127.0.0.1', '10013', '1539139114');
INSERT INTO `admin_log` VALUES ('300', '74', '1', '127.0.0.1', '', '1539141571');
INSERT INTO `admin_log` VALUES ('301', '74', '1', '127.0.0.1', '', '1539141590');
INSERT INTO `admin_log` VALUES ('302', '4', '1', '127.0.0.1', '676', '1539141703');
INSERT INTO `admin_log` VALUES ('303', '4', '1', '127.0.0.1', '677', '1539141747');
INSERT INTO `admin_log` VALUES ('304', '4', '1', '127.0.0.1', '676', '1539141767');
INSERT INTO `admin_log` VALUES ('305', '4', '1', '127.0.0.1', '677', '1539141776');
INSERT INTO `admin_log` VALUES ('306', '677', '1', '127.0.0.1', '', '1539141966');
INSERT INTO `admin_log` VALUES ('307', '677', '1', '127.0.0.1', '', '1539141980');
INSERT INTO `admin_log` VALUES ('308', '676', '1', '127.0.0.1', '7', '1539142173');
INSERT INTO `admin_log` VALUES ('309', '676', '1', '127.0.0.1', '8', '1539142498');
INSERT INTO `admin_log` VALUES ('310', '676', '1', '127.0.0.1', '9', '1539142687');
INSERT INTO `admin_log` VALUES ('311', '676', '1', '127.0.0.1', '8', '1539142718');
INSERT INTO `admin_log` VALUES ('312', '676', '1', '127.0.0.1', '8', '1539142726');
INSERT INTO `admin_log` VALUES ('313', '4', '1', '127.0.0.1', '678', '1539142849');
INSERT INTO `admin_log` VALUES ('314', '4', '1', '127.0.0.1', '678', '1539142876');
INSERT INTO `admin_log` VALUES ('315', '50', '1', '127.0.0.1', '', '1539220835');
INSERT INTO `admin_log` VALUES ('316', '676', '1', '127.0.0.1', '8', '1539221276');
INSERT INTO `admin_log` VALUES ('317', '4', '1', '127.0.0.1', '679', '1539223178');
INSERT INTO `admin_log` VALUES ('318', '4', '1', '127.0.0.1', '680', '1539223242');
INSERT INTO `admin_log` VALUES ('319', '4', '1', '127.0.0.1', '680', '1539223269');
INSERT INTO `admin_log` VALUES ('320', '679', '1', '127.0.0.1', '10', '1539238126');
INSERT INTO `admin_log` VALUES ('321', '679', '1', '127.0.0.1', '56', '1539238557');
INSERT INTO `admin_log` VALUES ('322', '73', '1', '127.0.0.1', '52', '1539239157');
INSERT INTO `admin_log` VALUES ('323', '673', '1', '127.0.0.1', '53', '1539239214');
INSERT INTO `admin_log` VALUES ('324', '676', '1', '127.0.0.1', '11', '1539239542');
INSERT INTO `admin_log` VALUES ('325', '679', '1', '127.0.0.1', '57', '1539239569');
INSERT INTO `admin_log` VALUES ('326', '679', '1', '127.0.0.1', '57', '1539239705');
INSERT INTO `admin_log` VALUES ('327', '4', '1', '127.0.0.1', '681', '1539240173');
INSERT INTO `admin_log` VALUES ('328', '4', '1', '127.0.0.1', '682', '1539241016');
INSERT INTO `admin_log` VALUES ('329', '4', '1', '127.0.0.1', '683', '1539241119');
INSERT INTO `admin_log` VALUES ('330', '4', '1', '127.0.0.1', '684', '1539251192');
INSERT INTO `admin_log` VALUES ('331', '73', '1', '127.0.0.1', '54', '1539251732');
INSERT INTO `admin_log` VALUES ('332', '673', '1', '127.0.0.1', '55', '1539251870');
INSERT INTO `admin_log` VALUES ('333', '684', '1', '127.0.0.1', '7', '1539251922');
INSERT INTO `admin_log` VALUES ('334', '684', '1', '127.0.0.1', '7', '1539253501');
INSERT INTO `admin_log` VALUES ('335', '684', '1', '127.0.0.1', '7', '1539253654');
INSERT INTO `admin_log` VALUES ('336', '4', '1', '127.0.0.1', '685', '1539253942');
INSERT INTO `admin_log` VALUES ('337', '50', '1', '127.0.0.1', '', '1539306239');
INSERT INTO `admin_log` VALUES ('338', '49', '1', '127.0.0.1', '16', '1539325259');
INSERT INTO `admin_log` VALUES ('339', '65', '1', '127.0.0.1', '10001', '1539325260');
INSERT INTO `admin_log` VALUES ('340', '4', '1', '127.0.0.1', '684', '1539326154');
INSERT INTO `admin_log` VALUES ('341', '4', '1', '127.0.0.1', '686', '1539326213');
INSERT INTO `admin_log` VALUES ('342', '4', '1', '127.0.0.1', '687', '1539326250');
INSERT INTO `admin_log` VALUES ('343', '65', '1', '127.0.0.1', '10006', '1539326369');
INSERT INTO `admin_log` VALUES ('344', '66', '1', '127.0.0.1', '10006', '1539326434');
INSERT INTO `admin_log` VALUES ('345', '66', '1', '127.0.0.1', '10006', '1539326438');
INSERT INTO `admin_log` VALUES ('346', '50', '1', '127.0.0.1', '', '1539566851');
INSERT INTO `admin_log` VALUES ('347', '4', '1', '127.0.0.1', '688', '1539572933');

-- ----------------------------
-- Table structure for `admin_menu`
-- ----------------------------
DROP TABLE IF EXISTS `admin_menu`;
CREATE TABLE `admin_menu` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `module` varchar(50) NOT NULL COMMENT '模块',
  `controller` varchar(100) NOT NULL COMMENT '控制器',
  `function` varchar(100) NOT NULL COMMENT '方法',
  `parameter` varchar(50) DEFAULT NULL COMMENT '参数',
  `description` varchar(250) DEFAULT NULL COMMENT '描述',
  `is_display` int(1) NOT NULL DEFAULT '1' COMMENT '1显示在左侧菜单2只作为节点',
  `type` int(1) NOT NULL DEFAULT '1' COMMENT '1权限节点2普通节点',
  `pid` int(11) NOT NULL DEFAULT '0' COMMENT '上级菜单0为顶级菜单',
  `create_time` int(11) NOT NULL,
  `update_time` int(11) NOT NULL,
  `icon` varchar(100) DEFAULT NULL COMMENT '图标',
  `is_open` int(1) NOT NULL DEFAULT '0' COMMENT '0默认闭合1默认展开',
  `orders` int(11) NOT NULL DEFAULT '0' COMMENT '排序值，越小越靠前',
  PRIMARY KEY (`id`),
  KEY `id` (`id`) USING BTREE,
  KEY `module` (`module`) USING BTREE,
  KEY `controller` (`controller`) USING BTREE,
  KEY `function` (`function`) USING BTREE,
  KEY `is_display` (`is_display`) USING BTREE,
  KEY `type` (`type`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=689 DEFAULT CHARSET=utf8 COMMENT='系统菜单表';

-- ----------------------------
-- Records of admin_menu
-- ----------------------------
INSERT INTO `admin_menu` VALUES ('1', '系统', '', '', '', '', '系统设置。', '1', '1', '0', '0', '1531988360', 'fa-cog', '0', '0');
INSERT INTO `admin_menu` VALUES ('2', '菜单', '', '', '', '', '菜单管理。', '1', '1', '1', '0', '1517015764', 'fa-paw', '0', '0');
INSERT INTO `admin_menu` VALUES ('51', '系统菜单排序', 'admin', 'menu', 'orders', '', '系统菜单排序。', '2', '1', '3', '1517562047', '1517562047', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('3', '系统菜单', 'admin', 'menu', 'index', '', '系统菜单管理', '1', '1', '2', '0', '0', 'fa-share-alt', '0', '0');
INSERT INTO `admin_menu` VALUES ('4', '新增/修改系统菜单', 'admin', 'menu', 'publish', '', '新增/修改系统菜单.', '2', '1', '3', '1516948769', '1516948769', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('5', '删除系统菜单', 'admin', 'menu', 'delete', '', '删除系统菜单。', '2', '1', '3', '1516948857', '1516948857', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('6', '个人', '', '', '', '', '个人信息管理。', '1', '1', '1', '1516949308', '1517021986', 'fa-user', '0', '0');
INSERT INTO `admin_menu` VALUES ('7', '个人信息', 'admin', 'admin', 'personal', '', '个人信息修改。', '1', '1', '6', '1516949435', '1516949435', 'fa-user', '0', '0');
INSERT INTO `admin_menu` VALUES ('8', '修改密码', 'admin', 'admin', 'editpassword', '', '管理员修改个人密码。', '1', '1', '6', '1516949702', '1517619887', 'fa-unlock-alt', '0', '0');
INSERT INTO `admin_menu` VALUES ('9', '设置', '', '', '', '', '系统相关设置。', '1', '1', '1', '1516949853', '1517015878', 'fa-cog', '0', '0');
INSERT INTO `admin_menu` VALUES ('10', '网站设置', 'admin', 'webconfig', 'index', '', '网站相关设置首页。', '1', '1', '9', '1516949994', '1516949994', 'fa-bullseye', '0', '0');
INSERT INTO `admin_menu` VALUES ('11', '修改网站设置', 'admin', 'webconfig', 'publish', '', '修改网站设置。', '2', '1', '10', '1516950047', '1516950047', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('12', '邮件设置', 'admin', 'emailconfig', 'index', '', '邮件配置首页。', '1', '1', '9', '1516950129', '1516950129', 'fa-envelope', '0', '0');
INSERT INTO `admin_menu` VALUES ('13', '修改邮件设置', 'admin', 'emailconfig', 'publish', '', '修改邮件设置。', '2', '1', '12', '1516950215', '1516950215', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('14', '发送测试邮件', 'admin', 'emailconfig', 'mailto', '', '发送测试邮件。', '2', '1', '12', '1516950295', '1516950295', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('15', '短信设置', 'admin', 'smsconfig', 'index', '', '短信设置首页。', '1', '1', '9', '1516950394', '1516950394', 'fa-comments', '0', '0');
INSERT INTO `admin_menu` VALUES ('16', '修改短信设置', 'admin', 'smsconfig', 'publish', '', '修改短信设置。', '2', '1', '15', '1516950447', '1516950447', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('17', '发送测试短信', 'admin', 'smsconfig', 'smsto', '', '发送测试短信。', '2', '1', '15', '1516950483', '1516950483', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('18', 'URL 设置', 'admin', 'urlsconfig', 'index', '', 'url 设置。', '1', '1', '9', '1516950738', '1516950804', 'fa-code-fork', '0', '0');
INSERT INTO `admin_menu` VALUES ('19', '新增/修改url设置', 'admin', 'urlsconfig', 'publish', '', '新增/修改url设置。', '2', '1', '18', '1516950850', '1516950850', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('20', '启用/禁用url美化', 'admin', 'urlsconfig', 'status', '', '启用/禁用url美化。', '2', '1', '18', '1516950909', '1516950909', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('21', ' 删除url美化规则', 'admin', 'urlsconfig', 'delete', '', ' 删除url美化规则。', '2', '1', '18', '1516950941', '1516950941', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('22', '会员', '', '', '', '', '会员管理。', '1', '1', '0', '1516950991', '1517015810', 'fa-users', '0', '0');
INSERT INTO `admin_menu` VALUES ('23', '管理员', '', '', '', '', '系统管理员管理。', '1', '1', '22', '1516951071', '1517015819', 'fa-user', '0', '0');
INSERT INTO `admin_menu` VALUES ('24', '管理员', 'admin', 'admin', 'index', '', '系统管理员列表。', '1', '1', '23', '1516951163', '1516951163', 'fa-user', '0', '0');
INSERT INTO `admin_menu` VALUES ('25', '新增/修改管理员', 'admin', 'admin', 'publish', '', '新增/修改系统管理员。', '2', '1', '24', '1516951224', '1516951224', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('26', '删除管理员', 'admin', 'admin', 'delete', '', '删除管理员。', '2', '1', '24', '1516951253', '1516951253', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('27', '权限组', 'admin', 'admin', 'admincate', '', '权限分组。', '1', '1', '23', '1516951353', '1517018168', 'fa-dot-circle-o', '0', '0');
INSERT INTO `admin_menu` VALUES ('28', '新增/修改权限组', 'admin', 'admin', 'admincatepublish', '', '新增/修改权限组。', '2', '1', '27', '1516951483', '1516951483', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('29', '删除权限组', 'admin', 'admin', 'admincatedelete', '', '删除权限组。', '2', '1', '27', '1516951515', '1516951515', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('30', '操作日志', 'admin', 'admin', 'log', '', '系统管理员操作日志。', '1', '1', '23', '1516951754', '1517018196', 'fa-pencil', '0', '0');
INSERT INTO `admin_menu` VALUES ('31', '内容', '', '', '', '', '内容管理。', '1', '1', '0', '1516952262', '1517015835', 'fa-th-large', '0', '0');
INSERT INTO `admin_menu` VALUES ('32', '文章', '', '', '', '', '文章相关管理。', '1', '1', '31', '1516952698', '1517015846', 'fa-bookmark', '0', '0');
INSERT INTO `admin_menu` VALUES ('33', '分类', 'admin', 'articlecate', 'index', '', '文章分类管理。', '1', '1', '32', '1516952856', '1516952856', 'fa-tag', '0', '0');
INSERT INTO `admin_menu` VALUES ('34', '新增/修改文章分类', 'admin', 'articlecate', 'publish', '', '新增/修改文章分类。', '2', '1', '33', '1516952896', '1516952896', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('35', '删除文章分类', 'admin', 'articlecate', 'delete', '', '删除文章分类。', '2', '1', '33', '1516952942', '1516952942', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('36', '文章', 'admin', 'article', 'index', '', '文章管理。', '1', '1', '32', '1516953011', '1516953028', 'fa-bookmark', '0', '0');
INSERT INTO `admin_menu` VALUES ('37', '新增/修改文章', 'admin', 'article', 'publish', '', '新增/修改文章。', '2', '1', '36', '1516953056', '1516953056', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('38', '审核/拒绝文章', 'admin', 'article', 'status', '', '审核/拒绝文章。', '2', '1', '36', '1516953113', '1516953113', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('39', '置顶/取消置顶文章', 'admin', 'article', 'is_top', '', '置顶/取消置顶文章。', '2', '1', '36', '1516953162', '1516953162', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('40', '删除文章', 'admin', 'article', 'delete', '', '删除文章。', '2', '1', '36', '1516953183', '1516953183', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('41', '附件', 'admin', 'attachment', 'index', '', '附件管理。', '1', '1', '31', '1516953306', '1516953306', 'fa-cube', '0', '0');
INSERT INTO `admin_menu` VALUES ('42', '附件审核', 'admin', 'attachment', 'audit', '', '附件审核。', '2', '1', '41', '1516953359', '1516953440', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('43', '附件上传', 'admin', 'attachment', 'upload', '', '附件上传。', '2', '1', '41', '1516953392', '1516953392', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('44', '附件下载', 'admin', 'attachment', 'download', '', '附件下载。', '2', '1', '41', '1516953430', '1516953430', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('45', '附件删除', 'admin', 'attachment', 'delete', '', '附件删除。', '2', '1', '41', '1516953477', '1516953477', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('46', '留言', 'admin', 'tomessages', 'index', '', '留言管理。', '1', '1', '31', '1516953526', '1516953526', 'fa-comments', '0', '0');
INSERT INTO `admin_menu` VALUES ('47', '留言处理', 'admin', 'tomessages', 'mark', '', '留言处理。', '2', '1', '46', '1516953605', '1516953605', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('48', '留言删除', 'admin', 'tomessages', 'delete', '', '留言删除。', '2', '1', '46', '1516953648', '1516953648', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('49', '图片上传', 'admin', 'common', 'upload', '', '图片上传。', '2', '2', '0', '1516954491', '1516954491', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('50', '管理员登录', 'admin', 'common', 'login', '', '管理员登录。', '2', '2', '0', '1516954517', '1516954517', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('52', '用户', '', '', '', '', '用户。', '1', '1', '0', '1531984998', '1531984998', 'fa-user', '0', '0');
INSERT INTO `admin_menu` VALUES ('53', '用户管理', '', '', '', '', '用户管理。', '1', '1', '52', '1531984998', '1531984998', 'fa-user', '0', '0');
INSERT INTO `admin_menu` VALUES ('54', '普通用户', 'admin', 'user', 'index', '', '用户端用户列表。', '1', '1', '53', '1531989478', '1531989478', 'fa-user-o', '0', '0');
INSERT INTO `admin_menu` VALUES ('55', '销售人员', 'admin', 'sale', 'index', '', '销售端用户列表。', '1', '1', '53', '1531989627', '1537863383', 'fa-user-circle', '0', '0');
INSERT INTO `admin_menu` VALUES ('56', '物业人员', 'admin', 'property', 'index', '', '物业端用户列表。', '1', '1', '53', '1531989667', '1531989667', 'fa-user-circle-o', '0', '0');
INSERT INTO `admin_menu` VALUES ('57', '区域', '', '', '', '', '区域', '1', '1', '0', '1531990907', '1531990907', 'fa-th-large', '0', '0');
INSERT INTO `admin_menu` VALUES ('58', '区域地铁管理', '', '', '', '', '区域/地铁设置。', '1', '1', '57', '1531991098', '1531991202', 'fa-th-large', '0', '0');
INSERT INTO `admin_menu` VALUES ('60', '我的门店', '', '', '', '', '', '1', '1', '0', '1532662554', '1532662554', 'fa-th-large', '0', '0');
INSERT INTO `admin_menu` VALUES ('61', '修改用户信息', 'admin', 'user', 'publish', '', '修改用户信息。', '2', '1', '54', '1537860119', '1537860119', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('62', '启用/禁用用户', 'admin', 'user', 'user_status', '', '启用/禁用用户。', '2', '1', '54', '1537862646', '1537862646', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('63', '修改销售人员信息', 'admin', 'sale', 'publish', '', '修改销售人员信息。', '2', '1', '55', '1537863683', '1537863683', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('64', '启用/禁用销售人员', 'admin', 'sale', 'sale_status', '', '启用/禁用销售人员。', '2', '1', '55', '1537863729', '1537863729', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('65', '修改物业人员信息', 'admin', 'property', 'publish', '', '修改物业人员信息。', '2', '1', '56', '1537863948', '1537863948', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('66', '启用/禁用物业人员', 'admin', 'property', 'property_status', '', '启用/禁用物业人员。', '2', '1', '56', '1537864849', '1537864849', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('67', '城市', 'admin', 'city', 'index', '', '城市列表', '1', '1', '58', '1537865450', '1537865450', 'fa-asterisk', '0', '0');
INSERT INTO `admin_menu` VALUES ('68', '一级区域', 'admin', 'area', 'index', '', '一级区域列表', '1', '1', '58', '1537865514', '1538213042', 'fa-asterisk', '0', '0');
INSERT INTO `admin_menu` VALUES ('69', '设为/取消热门城市', 'admin', 'city', 'is_hot', '', '设为/取消热门城市。', '2', '1', '67', '1537866980', '1537866980', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('70', '设为/取消城市显示', 'admin', 'city', 'is_show', '', '设为/取消城市显示。', '2', '1', '67', '1537929193', '1537929193', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('71', '新增/修改城市信息', 'admin', 'city', 'publish', '', '新增/修改城市信息。', '2', '1', '67', '1537929528', '1537929528', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('72', '城市排序', 'admin', 'city', 'paixu', '', '城市排序。', '2', '1', '67', '1537950792', '1537950792', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('73', '新增/修改一级区域', 'admin', 'area', 'publish', '', '新增/修改一级区域。', '2', '1', '68', '1538014141', '1538119630', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('74', '一级区域排序', 'admin', 'area', 'paixu', '', '一级区域排序。', '2', '1', '68', '1538014191', '1538213007', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('75', '省份', 'admin', 'province', 'index', '', '省份列表。', '1', '1', '58', '1537865430', '1538201868', 'fa-asterisk', '0', '0');
INSERT INTO `admin_menu` VALUES ('76', '新增/修改省份信息', 'admin', 'province', 'publish', '', '新增/修改省份信息。', '2', '1', '75', '1538202259', '1538202259', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('77', '省份排序', 'admin', 'province', 'paixu', '', '省份排序。', '2', '1', '75', '1538202328', '1538202328', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('672', '二级区域', 'admin', 'area', 'index2', '', '二级区域列表。', '1', '1', '58', '1538212883', '1538212883', 'fa-asterisk', '0', '0');
INSERT INTO `admin_menu` VALUES ('673', '新增/修改二级区域', 'admin', 'area', 'publish2', '', '新增/修改二级区域。', '2', '1', '672', '1538212930', '1538212960', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('674', '二级区域排序', 'admin', 'area', 'paixu', '', '二级区域排序。', '2', '1', '672', '1538212992', '1538212992', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('675', '地铁线路', 'admin', 'subwayLines', 'index', '', '地铁线路列表。', '1', '1', '58', '1538213155', '1539056525', 'fa-asterisk', '0', '0');
INSERT INTO `admin_menu` VALUES ('676', '新增/修改地铁线路', 'admin', 'subwayLines', 'publish', '', '新增/修改地铁线路。', '2', '1', '675', '1539141703', '1539141767', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('677', '地铁线路排序', 'admin', 'subwayLines', 'paixu', '', '地铁线路排序。', '2', '1', '675', '1539141747', '1539141776', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('678', '地铁站台', 'admin', 'subwayStation', 'index', '', '地铁站台列表。', '1', '1', '58', '1539142849', '1539142876', 'fa-asterisk', '0', '0');
INSERT INTO `admin_menu` VALUES ('679', '新增/修改地铁站台', 'admin', 'subwayStation', 'publish', '', '新增/修改地铁站台。', '2', '1', '678', '1539223178', '1539223178', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('680', '地铁站台排序', 'admin', 'subwayStation', 'paixu', '', '地铁站台排序。', '2', '1', '678', '1539223242', '1539223269', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('682', '小区管理', '', '', '', '', '小区管理。', '1', '1', '57', '1539241016', '1539241016', 'fa-asterisk', '0', '0');
INSERT INTO `admin_menu` VALUES ('683', '小区', 'admin', 'xiaoqu', 'index', '', '小区列表。', '1', '1', '682', '1539241119', '1539241119', 'fa-asterisk', '0', '0');
INSERT INTO `admin_menu` VALUES ('684', '新增/修改小区', 'admin', 'xiaoqu', 'publish', '', '新增/修改小区。', '2', '1', '683', '1539251192', '1539326154', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('685', '物业人员', 'admin', 'property', 'index', '', '物业人员列表。', '1', '1', '682', '1539253942', '1539253942', 'fa-asterisk', '0', '0');
INSERT INTO `admin_menu` VALUES ('686', '新增/修改物业人员', 'admin', 'property', 'publish', '', '修改物业人员信息。', '2', '1', '685', '1539326213', '1539326213', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('687', '启用/禁用物业人员', 'admin', 'property', 'property_status', '', '启用/禁用物业人员。', '2', '1', '685', '1539326250', '1539326250', '', '0', '0');
INSERT INTO `admin_menu` VALUES ('688', '超市管理', '', '', '', '', '超市管理。', '1', '1', '0', '1539572933', '1539572933', 'fa-asterisk', '0', '0');

-- ----------------------------
-- Table structure for `area`
-- ----------------------------
DROP TABLE IF EXISTS `area`;
CREATE TABLE `area` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `city_id` int(11) NOT NULL DEFAULT '1',
  `area_name1` varchar(20) NOT NULL COMMENT '一级区域',
  `area_name2` varchar(20) NOT NULL COMMENT '二级区域',
  `lng` varchar(20) NOT NULL,
  `lat` varchar(20) NOT NULL,
  `pid` int(11) NOT NULL,
  `paixu` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=56 DEFAULT CHARSET=utf8 COMMENT='区域信息表';

-- ----------------------------
-- Records of area
-- ----------------------------
INSERT INTO `area` VALUES ('1', '1', '锦江', '', '104.137705', '30.603166', '0', '1');
INSERT INTO `area` VALUES ('2', '1', '', '川师', '104.111810', '30.617846', '1', '3');
INSERT INTO `area` VALUES ('3', '1', '', '春熙路', '104.080068', '30.658588', '1', '0');
INSERT INTO `area` VALUES ('4', '1', '', '大慈寺', '104.085151', '30.654807', '1', '0');
INSERT INTO `area` VALUES ('5', '1', '', '大观', '104.136307', '30.613384', '1', '0');
INSERT INTO `area` VALUES ('6', '1', '', '东大街', '104.091352', '30.646861', '1', '0');
INSERT INTO `area` VALUES ('7', '1', '', '海椒市', '104.101440', '30.637650', '1', '0');
INSERT INTO `area` VALUES ('8', '1', '', '合江亭', '104.083627', '30.642950', '1', '0');
INSERT INTO `area` VALUES ('9', '1', '', '红星路', '104.083966', '30.661257', '1', '0');
INSERT INTO `area` VALUES ('10', '1', '', '九眼桥', '104.089582', '30.640330', '1', '0');
INSERT INTO `area` VALUES ('11', '1', '', '牛市口', '104.102530', '30.643339', '1', '0');
INSERT INTO `area` VALUES ('12', '1', '青羊', '', '104.062499', '30.674406', '0', '2');
INSERT INTO `area` VALUES ('13', '1', '', '八宝街', '104.060048', '30.671682', '12', '0');
INSERT INTO `area` VALUES ('14', '1', '', '白果林', '104.033692', '30.676528', '12', '0');
INSERT INTO `area` VALUES ('15', '1', '', '贝森', '104.000273', '30.669961', '12', '0');
INSERT INTO `area` VALUES ('16', '1', '', '草市街', '104.074963', '30.670512', '12', '0');
INSERT INTO `area` VALUES ('17', '1', '', '草堂', '104.022494', '30.654406', '12', '0');
INSERT INTO `area` VALUES ('18', '1', '', '府南新区', '104.022684', '30.678089', '12', '0');
INSERT INTO `area` VALUES ('19', '1', '', '金沙', '104.008411', '30.680345', '12', '0');
INSERT INTO `area` VALUES ('20', '1', '', '人民公园', '104.056779', '30.659324', '12', '0');
INSERT INTO `area` VALUES ('21', '1', '', '顺城街', '104.070741', '30.664558', '12', '0');
INSERT INTO `area` VALUES ('22', '1', '', '太升路', '104.076187', '30.664301', '12', '0');
INSERT INTO `area` VALUES ('23', '1', '', '天府广场', '104.065877', '30.656907', '12', '0');
INSERT INTO `area` VALUES ('24', '1', '', '文殊坊', '104.073317', '30.672997', '12', '0');
INSERT INTO `area` VALUES ('25', '1', '', '中医附院', '104.035967', '30.670611', '12', '0');
INSERT INTO `area` VALUES ('48', '1', '金牛', '', '104.045300', '30.693000', '0', '3');
INSERT INTO `area` VALUES ('50', '1', '武侯', '', '104.047994', '30.646094', '0', '4');
INSERT INTO `area` VALUES ('51', '1', '', '测试', '104.066541', '30.572269', '1', '0');
INSERT INTO `area` VALUES ('52', '4', '达川区', '', '107.511845', '31.196118', '0', '0');
INSERT INTO `area` VALUES ('53', '4', '', '南外镇', '107.506172', '31.200843', '52', '0');
INSERT INTO `area` VALUES ('54', '1', '成华', '', '104.066541', '30.572269', '0', '0');
INSERT INTO `area` VALUES ('55', '1', '', '大观', '103.575112', '30.844415', '54', '0');

-- ----------------------------
-- Table structure for `article`
-- ----------------------------
DROP TABLE IF EXISTS `article`;
CREATE TABLE `article` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `tag` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `article_cate_id` int(11) NOT NULL,
  `thumb` int(11) DEFAULT NULL,
  `content` text,
  `admin_id` int(11) NOT NULL,
  `create_time` int(11) NOT NULL,
  `update_time` int(11) NOT NULL,
  `edit_admin_id` int(11) NOT NULL COMMENT '最后修改人',
  `status` int(1) NOT NULL DEFAULT '0' COMMENT '0待审核1已审核',
  `is_top` int(1) NOT NULL DEFAULT '0' COMMENT '1置顶0普通',
  PRIMARY KEY (`id`),
  KEY `id` (`id`) USING BTREE,
  KEY `status` (`status`) USING BTREE,
  KEY `is_top` (`is_top`) USING BTREE,
  KEY `article_cate_id` (`article_cate_id`) USING BTREE,
  KEY `admin_id` (`admin_id`) USING BTREE,
  KEY `create_time` (`create_time`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of article
-- ----------------------------
INSERT INTO `article` VALUES ('1', '标题', '标签', '这是描述', '5', '6', '&lt;p&gt;内容&lt;/p&gt;', '1', '1537856793', '1537856841', '1', '1', '1');

-- ----------------------------
-- Table structure for `article_cate`
-- ----------------------------
DROP TABLE IF EXISTS `article_cate`;
CREATE TABLE `article_cate` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `tag` varchar(250) DEFAULT NULL COMMENT '关键词',
  `description` varchar(250) DEFAULT NULL,
  `create_time` int(11) NOT NULL,
  `update_time` int(11) NOT NULL,
  `pid` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `id` (`id`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of article_cate
-- ----------------------------
INSERT INTO `article_cate` VALUES ('5', '休闲', '标签', '备注', '1537856762', '1537856762', '0');

-- ----------------------------
-- Table structure for `attachment`
-- ----------------------------
DROP TABLE IF EXISTS `attachment`;
CREATE TABLE `attachment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `module` char(15) NOT NULL DEFAULT '' COMMENT '所属模块',
  `filename` char(50) NOT NULL DEFAULT '' COMMENT '文件名',
  `filepath` char(200) NOT NULL DEFAULT '' COMMENT '文件路径+文件名',
  `filesize` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '文件大小',
  `fileext` char(10) NOT NULL DEFAULT '' COMMENT '文件后缀',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '会员ID',
  `uploadip` char(15) NOT NULL DEFAULT '' COMMENT '上传IP',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0未审核1已审核-1不通过',
  `create_time` int(11) unsigned NOT NULL DEFAULT '0',
  `admin_id` int(11) NOT NULL COMMENT '审核者id',
  `audit_time` int(11) NOT NULL COMMENT '审核时间',
  `use` varchar(200) DEFAULT NULL COMMENT '用处',
  `download` int(11) NOT NULL DEFAULT '0' COMMENT '下载量',
  PRIMARY KEY (`id`),
  KEY `id` (`id`) USING BTREE,
  KEY `status` (`status`) USING BTREE,
  KEY `filename` (`filename`) USING BTREE,
  KEY `create_time` (`create_time`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='附件表';

-- ----------------------------
-- Records of attachment
-- ----------------------------
INSERT INTO `attachment` VALUES ('1', 'admin', '79811855a6c06de53047471c4ff82a36.jpg', '\\uploads\\admin\\admin_thumb\\20180104\\79811855a6c06de53047471c4ff82a36.jpg', '13781', 'jpg', '1', '127.0.0.1', '1', '1515046060', '1', '1515046060', 'admin_thumb', '0');
INSERT INTO `attachment` VALUES ('2', 'admin', '80eae3c51d92466f6aebdc2e808e4b74.jpg', '\\uploads\\admin\\admin_thumb\\20180716\\80eae3c51d92466f6aebdc2e808e4b74.jpg', '97495', 'jpg', '1', '127.0.0.1', '1', '1531712301', '0', '1531983793', 'admin_thumb', '0');
INSERT INTO `attachment` VALUES ('3', 'admin', '72c663185917146bc8ee29de49161460.jpg', '\\uploads\\admin\\admin_thumb\\20180720\\72c663185917146bc8ee29de49161460.jpg', '6588', 'jpg', '1', '127.0.0.1', '1', '1532070537', '1', '1532070537', 'admin_thumb', '0');
INSERT INTO `attachment` VALUES ('4', 'admin', 'b848eb05801a163e7e216ec9f9ba31c4.jpg', '\\uploads\\admin\\admin_thumb\\20180720\\b848eb05801a163e7e216ec9f9ba31c4.jpg', '15918', 'jpg', '1', '127.0.0.1', '1', '1532070638', '1', '1532070638', 'admin_thumb', '0');
INSERT INTO `attachment` VALUES ('5', 'admin', 'deae3dbbf4e1ab7566642182f191806e.jpg', '\\uploads\\admin\\admin_thumb\\20180727\\deae3dbbf4e1ab7566642182f191806e.jpg', '10632', 'jpg', '1', '127.0.0.1', '1', '1532662657', '1', '1532662657', 'admin_thumb', '0');
INSERT INTO `attachment` VALUES ('6', 'admin', 'bd25d65dedc94f27ff81fbd62f768e3a.jpg', '\\uploads\\admin\\article_thumb\\20180925\\bd25d65dedc94f27ff81fbd62f768e3a.jpg', '12000', 'jpg', '1', '127.0.0.1', '1', '1537856791', '1', '1537856791', 'article_thumb', '0');
INSERT INTO `attachment` VALUES ('7', 'admin', '684185bf0007f13aa02d1897ba6e832a.jpg', '\\uploads\\user\\avatar\\20180925\\684185bf0007f13aa02d1897ba6e832a.jpg', '12000', 'jpg', '1', '127.0.0.1', '1', '1537858998', '1', '1537858998', 'avatar', '0');
INSERT INTO `attachment` VALUES ('8', 'admin', '6580eef7122f4bdb5e908474b5d467a9.jpg', '\\uploads\\user\\avatar\\20180925\\6580eef7122f4bdb5e908474b5d467a9.jpg', '55696', 'jpg', '1', '127.0.0.1', '1', '1537859065', '1', '1537859065', 'avatar', '0');
INSERT INTO `attachment` VALUES ('9', 'admin', '6f82c4e010a3b9db720856e414da41d1.jpg', '\\uploads\\user\\avatar\\20180925\\6f82c4e010a3b9db720856e414da41d1.jpg', '55696', 'jpg', '1', '127.0.0.1', '1', '1537859180', '1', '1537859180', 'avatar', '0');
INSERT INTO `attachment` VALUES ('10', 'admin', 'cff9199a0442bc0a0ae6db2adc8a8845.jpg', '\\uploads\\user\\avatar\\20180925\\cff9199a0442bc0a0ae6db2adc8a8845.jpg', '55696', 'jpg', '1', '127.0.0.1', '1', '1537859415', '1', '1537859415', 'avatar', '0');
INSERT INTO `attachment` VALUES ('11', 'admin', '2e92a10e0943df2ae096f1afcdbe0864.jpg', '\\uploads\\user\\avatar\\20180925\\2e92a10e0943df2ae096f1afcdbe0864.jpg', '55696', 'jpg', '1', '127.0.0.1', '1', '1537859472', '1', '1537859472', 'avatar', '0');
INSERT INTO `attachment` VALUES ('12', 'admin', 'fbbad15fd5da933ce97873eeabe364b0.jpg', '\\uploads\\user\\avatar\\20180925\\fbbad15fd5da933ce97873eeabe364b0.jpg', '55696', 'jpg', '1', '127.0.0.1', '1', '1537859490', '1', '1537859490', 'avatar', '0');
INSERT INTO `attachment` VALUES ('13', 'admin', 'd6f64affb50e6d11253f03ec63becd01.jpg', '\\uploads\\user\\avatar\\20180925\\d6f64affb50e6d11253f03ec63becd01.jpg', '55696', 'jpg', '1', '127.0.0.1', '1', '1537859590', '1', '1537859590', 'avatar', '0');
INSERT INTO `attachment` VALUES ('14', 'admin', '19eaa4ebe4e88918502dfcba3af6ced2.jpg', '\\uploads\\user\\avatar\\20180925\\19eaa4ebe4e88918502dfcba3af6ced2.jpg', '12000', 'jpg', '1', '127.0.0.1', '1', '1537860142', '1', '1537860142', 'avatar', '0');
INSERT INTO `attachment` VALUES ('15', 'admin', '20e3d7a3fd0d3b267adc084e8274269d.jpg', '\\uploads\\admin\\admin_thumb\\20180925\\20e3d7a3fd0d3b267adc084e8274269d.jpg', '55696', 'jpg', '1', '127.0.0.1', '1', '1537860398', '1', '1537860398', 'admin_thumb', '0');
INSERT INTO `attachment` VALUES ('16', 'admin', '81fffa4cafc9da8629ea86892b31bffc.jpg', '\\uploads\\property\\avatar\\20181012\\81fffa4cafc9da8629ea86892b31bffc.jpg', '10632', 'jpg', '1', '127.0.0.1', '1', '1539325259', '1', '1539325259', 'avatar', '0');

-- ----------------------------
-- Table structure for `banner`
-- ----------------------------
DROP TABLE IF EXISTS `banner`;
CREATE TABLE `banner` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(50) NOT NULL,
  `url` varchar(255) NOT NULL,
  `cover` varchar(255) NOT NULL,
  `is_show` tinyint(4) NOT NULL DEFAULT '1' COMMENT '是否显示 1是 0否',
  `paixu` tinyint(4) NOT NULL DEFAULT '0' COMMENT '排序',
  `create_time` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of banner
-- ----------------------------
INSERT INTO `banner` VALUES ('1', '测试banner标题', 'https://www.baidu.com/', '/uploads/sale/house_img/20180802/d22dde5e105d2601d7cab3f95feef820.jpg', '1', '1', '2018-08-28 11:08:44');
INSERT INTO `banner` VALUES ('2', '测试banner2', 'https://www.taobao.com/', '/uploads/sale/house_img/20180802/da792446a6a42d75c7e103a4ab3d8510.jpg', '1', '2', '2018-08-28 11:09:20');

-- ----------------------------
-- Table structure for `city`
-- ----------------------------
DROP TABLE IF EXISTS `city`;
CREATE TABLE `city` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `province_id` int(11) NOT NULL,
  `city_name` varchar(50) NOT NULL,
  `is_hot` tinyint(4) NOT NULL,
  `is_show` tinyint(4) NOT NULL DEFAULT '1' COMMENT '是否显示 1是',
  `paixu` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=25 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of city
-- ----------------------------
INSERT INTO `city` VALUES ('1', '1', '成都', '1', '1', '2');
INSERT INTO `city` VALUES ('2', '1', '绵阳', '0', '0', '8');
INSERT INTO `city` VALUES ('3', '1', '南充', '0', '0', '9');
INSERT INTO `city` VALUES ('4', '1', '达州', '0', '0', '10');
INSERT INTO `city` VALUES ('5', '1', '乐山', '0', '0', '11');
INSERT INTO `city` VALUES ('6', '2', '北京', '1', '1', '1');
INSERT INTO `city` VALUES ('7', '3', '上海', '1', '1', '3');
INSERT INTO `city` VALUES ('8', '9', '重庆', '1', '1', '4');
INSERT INTO `city` VALUES ('9', '8', '青岛', '1', '1', '5');
INSERT INTO `city` VALUES ('10', '10', '西安', '1', '1', '6');
INSERT INTO `city` VALUES ('11', '5', '深圳', '1', '1', '7');
INSERT INTO `city` VALUES ('24', '11', '南京', '1', '0', '12');

-- ----------------------------
-- Table structure for `emailconfig`
-- ----------------------------
DROP TABLE IF EXISTS `emailconfig`;
CREATE TABLE `emailconfig` (
  `email` varchar(5) NOT NULL COMMENT '邮箱配置标识',
  `from_email` varchar(50) NOT NULL COMMENT '邮件来源也就是邮件地址',
  `from_name` varchar(50) NOT NULL,
  `smtp` varchar(50) NOT NULL COMMENT '邮箱smtp服务器',
  `username` varchar(100) NOT NULL COMMENT '邮箱账号',
  `password` varchar(100) NOT NULL COMMENT '邮箱密码',
  `title` varchar(200) NOT NULL COMMENT '邮件标题',
  `content` text NOT NULL COMMENT '邮件模板',
  KEY `email` (`email`) USING BTREE
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of emailconfig
-- ----------------------------
INSERT INTO `emailconfig` VALUES ('email', '', '', '', '', '', '', '');

-- ----------------------------
-- Table structure for `feedback`
-- ----------------------------
DROP TABLE IF EXISTS `feedback`;
CREATE TABLE `feedback` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `content` varchar(255) NOT NULL,
  `img_url` varchar(1000) NOT NULL,
  `user_id` int(11) NOT NULL,
  `is_read` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否已读 1是 0否',
  `create_time` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of feedback
-- ----------------------------
INSERT INTO `feedback` VALUES ('2', '我觉得这个app还需要优化！', '/uploads/user/user_avatar/20180726/e65621b4e0740293d95119546642a8c9.jpg,,,/uploads/user/user_avatar/20180726/8f200c00929083b824a3e9211d66ce5d.jpg,,,/uploads/user/user_avatar/20180726/b7b76680911a9fefcb54a85d4c85e56b.jpg', '10011', '0', '1532585073');
INSERT INTO `feedback` VALUES ('3', 'Siobhan is de. Faneuil', '/uploads/user/feedback_img/20180919/310ba56bdce1334ad10d73a4cdfa6c52.png,,,/uploads/user/feedback_img/20180919/1e305753cab3a346df8836a090acc900.png,,,/uploads/user/feedback_img/20180919/805b2021e283909df473b02b86a9e14f.png', '10014', '0', '1537324758');

-- ----------------------------
-- Table structure for `goods`
-- ----------------------------
DROP TABLE IF EXISTS `goods`;
CREATE TABLE `goods` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `goods_name` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL,
  `spec` varchar(20) NOT NULL COMMENT '规格',
  `unit` varchar(20) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `number` int(11) NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '状态 1上架 0下架',
  `sales` int(11) NOT NULL DEFAULT '0' COMMENT '销量 ',
  `ticheng` int(11) NOT NULL,
  `is_delete` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否已删除 1是',
  `create_time` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of goods
-- ----------------------------
INSERT INTO `goods` VALUES ('1', '1', '现代风格 名流之选 巴西进口头层黄牛皮 五档调节头枕左转角沙发', '长3880*宽1940*高840mm', '1.8米', '张', '0.01', '99', '1', '2', '0', '0', '0');
INSERT INTO `goods` VALUES ('2', '1', '简美风格 全实木框架 进口头层黄牛皮 舒服棉麻布艺 客厅皮布组合沙发 莱克星顿系列', '单人：长800*宽950*高1050mm； 两人：长1450*宽950*高1030mm； 三人：长2020*宽950*高1050mm', '2.5米', '张', '59.00', '99', '1', '0', '0', '0', '0');
INSERT INTO `goods` VALUES ('3', '1', '美式田园 名师力荐 优质实木内架 内置高密度海绵 全真皮沙发套装', '单人位：长1100*宽980*高950mm；两人位:长1600*宽980*高950mm；三人位:长2160*宽980*高950mm', '3.3米', '张', '69.00', '99', '1', '0', '0', '0', '0');

-- ----------------------------
-- Table structure for `goods_class`
-- ----------------------------
DROP TABLE IF EXISTS `goods_class`;
CREATE TABLE `goods_class` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `pid` int(11) NOT NULL,
  `class_name` varchar(20) NOT NULL,
  `paixu` int(11) NOT NULL,
  `is_show` int(11) NOT NULL DEFAULT '1' COMMENT '是否显示 1是 0否',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of goods_class
-- ----------------------------
INSERT INTO `goods_class` VALUES ('1', '0', '家居', '0', '1');
INSERT INTO `goods_class` VALUES ('2', '0', '电器', '0', '1');
INSERT INTO `goods_class` VALUES ('4', '0', '生活用品', '0', '1');

-- ----------------------------
-- Table structure for `goods_comment`
-- ----------------------------
DROP TABLE IF EXISTS `goods_comment`;
CREATE TABLE `goods_comment` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `goods_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `content` varchar(255) NOT NULL,
  `score` float(2,1) NOT NULL COMMENT '评分',
  `create_time` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of goods_comment
-- ----------------------------
INSERT INTO `goods_comment` VALUES ('1', '10011', '1', '0', '感觉不错诶，是真皮沙发！', '5.0', '1533192293');
INSERT INTO `goods_comment` VALUES ('2', '10012', '1', '0', '便宜又实惠！', '5.0', '1533592293');
INSERT INTO `goods_comment` VALUES ('3', '10011', '1', '0', '又买了一次，值得推荐！', '3.5', '1533722293');
INSERT INTO `goods_comment` VALUES ('4', '10011', '1', '14', '价钱便宜', '5.0', '1533622531');
INSERT INTO `goods_comment` VALUES ('5', '10011', '2', '14', '还将就', '3.5', '1533622531');
INSERT INTO `goods_comment` VALUES ('6', '10014', '1', '20', '一般吧', '5.0', '1539244425');

-- ----------------------------
-- Table structure for `goods_img`
-- ----------------------------
DROP TABLE IF EXISTS `goods_img`;
CREATE TABLE `goods_img` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `goods_id` int(11) NOT NULL,
  `img_url` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of goods_img
-- ----------------------------
INSERT INTO `goods_img` VALUES ('1', '1', '1.jpg');
INSERT INTO `goods_img` VALUES ('2', '1', '2.jpg');
INSERT INTO `goods_img` VALUES ('3', '1', '3.jpg');
INSERT INTO `goods_img` VALUES ('4', '2', '1.jpg');
INSERT INTO `goods_img` VALUES ('5', '2', '2.jpg');
INSERT INTO `goods_img` VALUES ('6', '2', '3.jpg');
INSERT INTO `goods_img` VALUES ('7', '3', '1.jpg');
INSERT INTO `goods_img` VALUES ('8', '3', '2.jpg');
INSERT INTO `goods_img` VALUES ('9', '3', '3.jpg');

-- ----------------------------
-- Table structure for `goods_order`
-- ----------------------------
DROP TABLE IF EXISTS `goods_order`;
CREATE TABLE `goods_order` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(20) NOT NULL,
  `shouhuo_username` varchar(20) NOT NULL,
  `shouhuo_mobile` char(11) NOT NULL,
  `shouhuo_address` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `pay_money` decimal(10,2) NOT NULL,
  `pay_type` varchar(20) NOT NULL,
  `pay_time` int(11) NOT NULL DEFAULT '0' COMMENT '支付时间',
  `order_status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '订单状态 1待支付 2待发货 3待收货 4待评价 5已完成 -2取消订单(已付款时取消) -1取消订单(未支付时取消) -3拒收',
  `distribution_status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '配送状态 1待配送 2配送中',
  `sale_id` int(11) NOT NULL COMMENT '配送人id',
  `confirm_time` int(11) NOT NULL DEFAULT '0' COMMENT '确认时间',
  `cancel_time` int(11) NOT NULL DEFAULT '0' COMMENT '取消时间',
  `create_time` int(11) NOT NULL,
  `user_is_del` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=23 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of goods_order
-- ----------------------------
INSERT INTO `goods_order` VALUES ('12', 'G20180807364230', '张甜甜', '15881050321', '四川省成都市武侯区', '10011', '59.00', '', '0', '-3', '2', '10000', '0', '1533607806', '1533606212', '0');
INSERT INTO `goods_order` VALUES ('14', 'G20180807862301', '张甜甜', '15881050321', '四川省成都市武侯区', '10011', '135.00', '支付宝', '1533610828', '5', '1', '10000', '1533610828', '0', '1533610659', '0');
INSERT INTO `goods_order` VALUES ('15', 'G20180829328202', '张甜甜', '15881050321', '四川省成都市武侯区', '10011', '135.00', '微信', '0', '-3', '2', '10000', '0', '0', '1535533061', '0');
INSERT INTO `goods_order` VALUES ('16', 'G20180912671257', '杨东', '15258523695', '四川省成都市成华区', '10013', '166.00', '', '0', '-1', '1', '10000', '0', '1536723706', '1536716137', '1');
INSERT INTO `goods_order` VALUES ('17', 'G20180927126844', '胖胖', '18200000000', '四川省成都市锦江区', '10014', '76.00', '', '0', '-1', '1', '0', '0', '1538981870', '1538029811', '1');
INSERT INTO `goods_order` VALUES ('18', 'G20180927417618', '胖胖', '18200000000', '四川省成都市锦江区', '10014', '118.00', '', '0', '-2', '1', '0', '0', '1539223649', '1538038337', '0');
INSERT INTO `goods_order` VALUES ('19', 'G20180928638083', '胖胖', '18200000000', '四川省成都市锦江区', '10014', '97.00', '支付宝', '0', '2', '1', '10001', '0', '0', '1538101232', '0');
INSERT INTO `goods_order` VALUES ('20', 'G20181008064439', '胖胖', '18200000000', '四川省成都市锦江区', '10014', '0.01', '支付宝', '0', '5', '2', '10001', '1539239596', '0', '1538986198', '0');
INSERT INTO `goods_order` VALUES ('21', 'G20181010490990', '杨东', '15258523695', '四川省成都市成华区', '10013', '0.01', '微信', '0', '1', '1', '0', '0', '0', '1539139131', '0');
INSERT INTO `goods_order` VALUES ('22', 'G20181010546125', '杨东', '15258523695', '四川省成都市成华区', '10013', '0.01', '支付宝', '0', '1', '1', '0', '0', '0', '1539139973', '0');

-- ----------------------------
-- Table structure for `goods_order_detail`
-- ----------------------------
DROP TABLE IF EXISTS `goods_order_detail`;
CREATE TABLE `goods_order_detail` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `goods_id` int(11) NOT NULL,
  `number` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=19 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of goods_order_detail
-- ----------------------------
INSERT INTO `goods_order_detail` VALUES ('2', '12', '2', '1', '59.00');
INSERT INTO `goods_order_detail` VALUES ('5', '14', '1', '2', '38.00');
INSERT INTO `goods_order_detail` VALUES ('6', '14', '2', '1', '59.00');
INSERT INTO `goods_order_detail` VALUES ('7', '15', '1', '2', '38.00');
INSERT INTO `goods_order_detail` VALUES ('8', '15', '2', '1', '59.00');
INSERT INTO `goods_order_detail` VALUES ('9', '16', '3', '1', '69.00');
INSERT INTO `goods_order_detail` VALUES ('10', '16', '2', '1', '59.00');
INSERT INTO `goods_order_detail` VALUES ('11', '16', '1', '1', '38.00');
INSERT INTO `goods_order_detail` VALUES ('12', '17', '1', '2', '38.00');
INSERT INTO `goods_order_detail` VALUES ('13', '18', '2', '2', '59.00');
INSERT INTO `goods_order_detail` VALUES ('14', '19', '2', '1', '59.00');
INSERT INTO `goods_order_detail` VALUES ('15', '19', '1', '1', '38.00');
INSERT INTO `goods_order_detail` VALUES ('16', '20', '1', '1', '0.01');
INSERT INTO `goods_order_detail` VALUES ('17', '21', '1', '1', '0.01');
INSERT INTO `goods_order_detail` VALUES ('18', '22', '1', '1', '0.01');

-- ----------------------------
-- Table structure for `house`
-- ----------------------------
DROP TABLE IF EXISTS `house`;
CREATE TABLE `house` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `rent` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '租金 单位 元/月',
  `rent_mode` tinyint(4) NOT NULL DEFAULT '1' COMMENT '租金方式 1押一付一 2押一付三 3半年付  4年付',
  `type` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1整租 2合租 3整租合租',
  `decoration_mode` tinyint(4) NOT NULL DEFAULT '1' COMMENT '装修方式 1简装 2精装',
  `bedroom_number` tinyint(4) NOT NULL DEFAULT '1' COMMENT '卧室数量 /室',
  `parlour_number` tinyint(4) NOT NULL DEFAULT '1' COMMENT '客厅数量 /厅',
  `toilet_number` tinyint(4) NOT NULL DEFAULT '1' COMMENT '卫生间数量 /卫',
  `acreage` int(11) NOT NULL DEFAULT '0' COMMENT '面积 /平方米',
  `floor_type` tinyint(4) NOT NULL DEFAULT '1' COMMENT '楼层类型 1低楼层 2中楼层 3高楼层',
  `floor` int(11) NOT NULL DEFAULT '0' COMMENT '当前楼层数',
  `total_floor` int(11) NOT NULL DEFAULT '0' COMMENT '总楼层数',
  `orientation` varchar(10) NOT NULL DEFAULT '南北' COMMENT '朝向 ',
  `house_type_id` int(11) NOT NULL DEFAULT '0' COMMENT '类型id',
  `years` varchar(20) NOT NULL DEFAULT '2018' COMMENT '年代',
  `is_elevator` tinyint(4) NOT NULL COMMENT '电梯 1有电梯 0无电梯',
  `city_id` int(11) NOT NULL,
  `area_id1` int(11) NOT NULL,
  `area_id2` int(11) NOT NULL,
  `xiaoqu_id` int(11) NOT NULL DEFAULT '0',
  `xiaoqu_name` varchar(50) NOT NULL,
  `address` varchar(255) NOT NULL,
  `is_subway` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否有地铁 1是 0否',
  `lines_id` int(11) NOT NULL DEFAULT '0' COMMENT '地铁线路id',
  `station_id` int(11) NOT NULL DEFAULT '0' COMMENT '地铁站台id',
  `tag_id` varchar(50) DEFAULT '' COMMENT '房屋标签id',
  `room_config_id` varchar(50) DEFAULT '' COMMENT '房间配置id',
  `entrust_id` int(11) NOT NULL DEFAULT '0' COMMENT '委托id',
  `is_appoint` tinyint(4) DEFAULT '0' COMMENT '是否指定其他销售 1指定 0未指定',
  `shop_id` int(11) NOT NULL COMMENT '店铺id',
  `sale_id` int(11) NOT NULL COMMENT '销售id',
  `add_sale_id` int(11) NOT NULL DEFAULT '0' COMMENT '发布房源销售id',
  `entrust_username` varchar(20) DEFAULT '' COMMENT '委托人',
  `entrust_mobile` char(11) DEFAULT '' COMMENT '委托电话',
  `source` tinyint(4) DEFAULT '0' COMMENT '1房东 2物业',
  `is_recommend` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否推荐 1是 0否',
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '房源状态 1待提交 2待审核 3审核成功 4审核失败 5已下架',
  `renting_status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '出租状态 1待租 2已定 3已租 4完结',
  `create_time` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8 COMMENT='长租房源表';

-- ----------------------------
-- Records of house
-- ----------------------------
INSERT INTO `house` VALUES ('2', '金开华府 无中介拎包入住 七天无理由退房 魔方公寓 家电齐全 押一付一', '公寓位于青羊区北大街2号（距离文殊院地铁K出口500米左右，皮肤病医院对面，金开华府小区内），是魔方生活服务集团成都分公司的旗舰店，总共18层， 267个房间，公寓配备两部电梯。所有房间都是独门独户一居室，配有独立的厨房和独立的卫生间，部分房间带独立客厅，所有房间配备宽带，适合2人居住。包含多功能的免费健身区、阅读区和商务谈判区，配有智能快递柜和独立快递室，以及舒适的家庭影院。', '0.02', '1', '1', '1', '1', '1', '1', '39', '2', '11', '23', '南北', '1', '2010', '1', '1', '12', '24', '2', '金开华府 ', '北大街2号', '1', '1', '5', '1,2,3,4,5', '1,2,3,4,5,6,7,8,9,10,11,12,13', '5', '0', '1', '10000', '0', '夏天', '15881050779', '1', '1', '3', '1', '1533192293');
INSERT INTO `house` VALUES ('3', '金开华府 精装套一 无中介拎包入住 七天无理由退房', '公寓位于青羊区北大街2号（距离文殊院地铁K出口500米左右，皮肤病医院对面，金开华府小区内），是魔方生活服务集团成都分公司的旗舰店，总共18层， 267个房间，公寓配备两部电梯。所有房间都是独门独户一居室，配有独立的厨房和独立的卫生间，部分房间带独立客厅，所有房间配备宽带，适合2人居住。包含多功能的免费健身区、阅读区和商务谈判区，配有智能快递柜和独立快递室，以及舒适的家庭影院。', '1800.00', '2', '2', '2', '1', '1', '1', '45', '2', '15', '23', '南北', '1', '2018', '1', '1', '12', '24', '2', '金开华府 ', '北大街2号', '1', '1', '5', '2,3,4,5', '1,2,3,4,5,6,7,8,9,10,11,12,13', '6', '0', '1', '10000', '0', '李长春', '15881050779', '1', '1', '3', '1', '1533192293');
INSERT INTO `house` VALUES ('4', '星乐家园套二 家居齐全 1800 半年付', '公寓位于青羊区北大街2号（距离文殊院地铁K出口500米左右，皮肤病医院对面，金开华府小区内），是魔方生活服务集团成都分公司的旗舰店，总共18层， 267个房间，公寓配备两部电梯。所有房间都是独门独户一居室，配有独立的厨房和独立的卫生间，部分房间带独立客厅，所有房间配备宽带，适合2人居住。包含多功能的免费健身区、阅读区和商务谈判区，配有智能快递柜和独立快递室，以及舒适的家庭影院。', '2200.00', '3', '2', '2', '2', '1', '1', '66', '3', '24', '30', '南北', '2', '2018', '1', '1', '12', '24', '1', '星乐家园', '华府大道238号', '1', '1', '8', '3,4,5,6', '1,2,3,4,5,6,7,8,9,10,11,12,13', '7', '0', '1', '10000', '0', '刘雪亮', '15881050779', '1', '1', '3', '3', '1533192293');
INSERT INTO `house` VALUES ('5', '金开华府二期 套三可整租合租 环境优美', '公寓位于青羊区北大街2号（距离文殊院地铁K出口500米左右，皮肤病医院对面，金开华府小区内），是魔方生活服务集团成都分公司的旗舰店，总共18层， 267个房间，公寓配备两部电梯。所有房间都是独门独户一居室，配有独立的厨房和独立的卫生间，部分房间带独立客厅，所有房间配备宽带，适合2人居住。包含多功能的免费健身区、阅读区和商务谈判区，配有智能快递柜和独立快递室，以及舒适的家庭影院。', '2400.00', '1', '3', '2', '3', '1', '1', '56', '2', '18', '28', '南北', '1', '2018', '1', '1', '12', '24', '2', '金开华府', '北大街2号', '1', '1', '5', '2,3,4,5,6', '1,2,3,4,5,6,7,8,9,10,11,12,13', '0', '0', '1', '10000', '10000', '马先生', '15880808080', '1', '1', '3', '1', '1533192293');
INSERT INTO `house` VALUES ('8', '无中介拎包入住 七天无理由退房 魔方公寓 家电齐全 门禁系统', '公寓位于青羊区北大街2号（距离文殊院地铁K出口500米左右，皮肤病医院对面，金开华府小区内），是魔方生活服务集团成都分公司的旗舰店，总共18层， 267个房间，公寓配备两部电梯。所有房间都是独门独户一居室，配有独立的厨房和独立的卫生间，部分房间带独立客厅，所有房间配备宽带，适合2人居住。包含多功能的免费健身区、阅读区和商务谈判区，配有智能快递柜和独立快递室，以及舒适的家庭影院。', '2430.00', '2', '1', '2', '1', '1', '1', '39', '2', '11', '23', '南北', '1', '2010', '1', '1', '12', '24', '2', '金开华府', '北大街2号', '1', '1', '5', '1,2,3,4,5', '1,2,3,4,5,6,7,8,9,10,11,12,13', '0', '0', '1', '10000', '10000', '马先生', '15880808080', '1', '0', '2', '1', '1534317251');
INSERT INTO `house` VALUES ('10', '天府三街 精装大套三', '近地铁', '3500.00', '2', '1', '2', '3', '2', '2', '120', '2', '10', '28', '东南', '2', '2013', '1', '1', '12', '24', '1', '罗马国际', '青龙街18号', '1', '1', '5', '1,3,4,5,6', '1,2,3,4,5,6,7,8,9,10,11,12,13', '10', '0', '1', '10000', '0', '杨东', '13888888888', '2', '0', '2', '1', '1534931395');
INSERT INTO `house` VALUES ('12', '新希望国际  精装大套三 120平 近地铁 ', '新希望国际  精装大套三 120平 近地铁新希望国际  精装大套三 120平 近地铁 新希望国际  精装大套三 120平 近地铁 ', '3600.00', '3', '3', '2', '3', '2', '2', '130', '1', '5', '26', '东南', '2', '2014', '1', '1', '12', '24', '1', '罗马国际', '青龙街18号', '1', '1', '5', '1,2,3,4,5,6', '1,2,3,7,8,11,12', '0', '0', '1', '10000', '10000', '王栋', '13666666666', '1', '0', '2', '1', '1534990304');
INSERT INTO `house` VALUES ('13', '罗马国际小区', '哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈吧', '3300.00', '3', '1', '2', '3', '2', '2', '135', '2', '12', '34', '东', '1', '2014', '1', '1', '12', '24', '1', '罗马国际', '青龙街18号', '1', '1', '23', '', '', '9', '0', '1', '10001', '0', '肖某某', '18000000000', '2', '0', '3', '1', '1535013401');
INSERT INTO `house` VALUES ('14', '南湖半岛  精装大套三', '价格便宜，性价比超高，离地铁1号线华阳站一公里，附近就有家乐福沃尔玛，彼临南湖公园，', '3800.00', '2', '1', '2', '3', '2', '2', '160', '2', '25', '33', '南', '2', '2012', '1', '1', '12', '24', '1', '罗马国际', '青龙街18号', '1', '1', '5', '2,3,4,5', '1,3,4,6,7,8,9,11,12,13', '8', '0', '1', '10000', '0', '夏天', '15881050779', '1', '0', '2', '1', '1535016219');
INSERT INTO `house` VALUES ('15', '合租 五个房间 先到先的', '健康的开发和肺结核看回放忽然胡混计划 iu 好呢发呼唤你健康iu 回家呢u 和你结婚副 hi 金兰呢', '800.00', '1', '2', '1', '4', '2', '4', '145', '1', '6', '36', '东', '2', '2016', '1', '1', '12', '24', '2', '金开华府 ', '北大街2号', '1', '1', '21', '1,3,5,6', '1,2,3,4,8,7,6,5,9,10,11,12,13', '11', '0', '1', '10001', '0', '小李', '18909999999', '2', '0', '4', '1', '1535089923');
INSERT INTO `house` VALUES ('17', '金开华府 精装套一 无中介拎包入住 七天无理由退房', '公寓位于青羊区北大街2号（距离文殊院地铁K出口500米左右，皮肤病医院对面，金开华府小区内），是魔方生活服务集团成都分公司的旗舰店，总共18层， 267个房间，公寓配备两部电梯。所有房间都是独门独户一居室，配有独立的厨房和独立的卫生间，部分房间带独立客厅，所有房间配备宽带，适合2人居住。包含多功能的免费健身区、阅读区和商务谈判区，配有智能快递柜和独立快递室，以及舒适的家庭影院。', '1800.00', '2', '2', '2', '1', '1', '1', '45', '2', '15', '23', '南北', '1', '2018', '1', '1', '12', '24', '2', '金开华府 ', '北大街2号', '1', '1', '5', '2,3,4,5', '1,2,3,4,5,6,7,8,9,10,11,12,13', '6', '0', '1', '10000', '0', '李长春', '15881050779', '1', '0', '2', '1', '1535097917');
INSERT INTO `house` VALUES ('18', '自己发布的房源', '哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈', '1600.00', '1', '1', '1', '1', '1', '1', '24', '1', '12', '43', '东', '1', '2014', '1', '1', '12', '24', '2', '金开华府 ', '北大街2号', '1', '1', '4', '2,3,4', '2,3,6,1,8,9,11,13', '0', '1', '1', '10001', '10001', '小王', '18200000000', '2', '0', '3', '1', '1535336347');
INSERT INTO `house` VALUES ('19', '自己发布的整租合租房源', '滴滴答答滴滴答答滴滴答答滴滴答答滴滴答答滴滴答答的', '950.00', '1', '3', '1', '4', '1', '3', '150', '1', '6', '20', '南', '2', '2017', '1', '1', '12', '24', '4', '星乐家园', '华府大道238号', '1', '2', '25', '1,3,5', '1,2,3,4,8,7,6,9,10,11,12,13', '0', '0', '1', '10001', '10001', '小李', '18300000000', '1', '0', '4', '1', '1535339293');

-- ----------------------------
-- Table structure for `house_collection`
-- ----------------------------
DROP TABLE IF EXISTS `house_collection`;
CREATE TABLE `house_collection` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `house_id` int(11) NOT NULL,
  `create_time` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of house_collection
-- ----------------------------
INSERT INTO `house_collection` VALUES ('2', '10011', '2', '1534148877');
INSERT INTO `house_collection` VALUES ('7', '10014', '5', '1539077964');

-- ----------------------------
-- Table structure for `house_entrust`
-- ----------------------------
DROP TABLE IF EXISTS `house_entrust`;
CREATE TABLE `house_entrust` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(20) NOT NULL,
  `mobile` varchar(20) NOT NULL,
  `province` varchar(20) NOT NULL,
  `city` varchar(20) NOT NULL,
  `area` varchar(20) NOT NULL,
  `address` varchar(255) NOT NULL,
  `description` varchar(500) NOT NULL,
  `type` tinyint(4) NOT NULL COMMENT '委托类型 1用户委托 2物业委托',
  `param_id` int(11) NOT NULL COMMENT '参数id  用户uid或者物业uid',
  `shop_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `lng` varchar(20) NOT NULL,
  `lat` varchar(20) NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '录入状态 1已录入 0未录入',
  `create_time` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8 COMMENT='房屋委托表';

-- ----------------------------
-- Records of house_entrust
-- ----------------------------
INSERT INTO `house_entrust` VALUES ('5', '夏天', '15881050779', '四川', '成都', '青羊', '北大街2号', '公寓位于青羊区北大街2号', '1', '10011', '1', '10000', '', '', '1', '1533111528');
INSERT INTO `house_entrust` VALUES ('6', '李长春', '15881050779', '四川', '成都', '青羊', '青龙街18号', '乐乎城市青年社区物业直租!', '1', '10011', '1', '10000', '', '', '1', '1533197176');
INSERT INTO `house_entrust` VALUES ('7', '刘雪亮', '15881050779', '四川', '成都', '青羊', '北打铜街4号', '生活设施齐全 交通便利 市中心青年公寓 年轻人的选择 拎包入住 舒适的环境 精装的房屋 让你的生活充满仪式感', '1', '10011', '1', '10000', '', '', '1', '1533197261');
INSERT INTO `house_entrust` VALUES ('8', '夏天', '15881050779', '四川', '成都', '青羊', '青龙街18号', '乐乎城市青年社区物业直租!', '1', '10011', '1', '10000', '104.064709', '30.669087', '1', '1534495974');
INSERT INTO `house_entrust` VALUES ('9', '肖某某', '18000000000', '四川', '成都', '锦江', 'XXXX街道XXXXXX小区', '哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈', '2', '1', '1', '10001', '104.080989', '30.657689', '1', '1534835750');
INSERT INTO `house_entrust` VALUES ('10', '杨东', '13888888888', '四川省', '成都市', '成华区', '青羊区清水河小区', '房子刚刚空出来', '2', '10001', '1', '10000', '104.228954', '30.656650', '1', '1534836198');
INSERT INTO `house_entrust` VALUES ('11', '小李', '18909999999', '四川', '成都', '青羊', '清江东路334号', '老小区 内装修不错 看起来还是很新', '2', '1', '1', '10001', '104.039896', '30.666627', '1', '1535073278');
INSERT INTO `house_entrust` VALUES ('12', '小杜', '18282828282', '四川', '成都', '锦江', '金江大道金和小区1单元', '精装修 小公寓 哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈哈', '2', '1', '1', '10001', '104.078054', '30.588625', '0', '1535073356');
INSERT INTO `house_entrust` VALUES ('13', '杨杰', '13232323232', '四川省', '成都市', '成华区', '府青路一段23号', '120大套三，精装修，家具家电齐全，一环路，进地铁，出行方便，月租金不少于3200，否则免谈', '1', '10013', '0', '0', '', '', '0', '1536809174');
INSERT INTO `house_entrust` VALUES ('14', '小莉', '18282828282', '四川', '成都', '锦江', '随便写详细地址吧', '房子很好 房子很好 房子很好的', '1', '10014', '0', '0', '', '', '0', '1537328706');

-- ----------------------------
-- Table structure for `house_img`
-- ----------------------------
DROP TABLE IF EXISTS `house_img`;
CREATE TABLE `house_img` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `house_id` int(11) NOT NULL,
  `img_url` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of house_img
-- ----------------------------
INSERT INTO `house_img` VALUES ('7', '2', '/uploads/sale/house_img/20180802/d22dde5e105d2601d7cab3f95feef820.jpg');
INSERT INTO `house_img` VALUES ('8', '2', '/uploads/sale/house_img/20180802/da792446a6a42d75c7e103a4ab3d8510.jpg');
INSERT INTO `house_img` VALUES ('9', '2', '/uploads/sale/house_img/20180802/58d69feaf2ff20347241a2ce841a3b2c.jpg');
INSERT INTO `house_img` VALUES ('10', '2', '/uploads/sale/house_img/20180802/b0a1dbcd1639662c09db3880e3291442.jpg');
INSERT INTO `house_img` VALUES ('11', '2', '/uploads/sale/house_img/20180802/f9eb18997f5fa41a5d33c29c71df66c0.jpg');
INSERT INTO `house_img` VALUES ('12', '2', '/uploads/sale/house_img/20180802/21a9f7302baecaff72e43ed3be4b0af7.jpg');
INSERT INTO `house_img` VALUES ('13', '3', '/uploads/sale/house_img/20180802/d22dde5e105d2601d7cab3f95feef820.jpg');
INSERT INTO `house_img` VALUES ('14', '3', '/uploads/sale/house_img/20180802/21a9f7302baecaff72e43ed3be4b0af7.jpg');
INSERT INTO `house_img` VALUES ('15', '3', '/uploads/sale/house_img/20180802/b0a1dbcd1639662c09db3880e3291442.jpg');
INSERT INTO `house_img` VALUES ('16', '4', '/uploads/sale/house_img/20180802/d22dde5e105d2601d7cab3f95feef820.jpg');
INSERT INTO `house_img` VALUES ('17', '4', '/uploads/sale/house_img/20180802/da792446a6a42d75c7e103a4ab3d8510.jpg');
INSERT INTO `house_img` VALUES ('18', '4', '/uploads/sale/house_img/20180802/58d69feaf2ff20347241a2ce841a3b2c.jpg');
INSERT INTO `house_img` VALUES ('19', '5', '/uploads/sale/house_img/20180802/58d69feaf2ff20347241a2ce841a3b2c.jpg');
INSERT INTO `house_img` VALUES ('20', '5', '/uploads/sale/house_img/20180802/21a9f7302baecaff72e43ed3be4b0af7.jpg');
INSERT INTO `house_img` VALUES ('21', '5', '/uploads/sale/house_img/20180802/f9eb18997f5fa41a5d33c29c71df66c0.jpg');
INSERT INTO `house_img` VALUES ('29', '8', '/uploads/sale/house_img/20180815/d2ea7d16c44dbdf9f28e8fba7b7f5858.jpg');
INSERT INTO `house_img` VALUES ('30', '8', '/uploads/sale/house_img/20180815/fe2d526a8aada36199dec622fd28b4d4.jpg');
INSERT INTO `house_img` VALUES ('31', '8', '/uploads/sale/house_img/20180815/8ffcd73307244c4c56e69c7e9d6a0dae.jpg');
INSERT INTO `house_img` VALUES ('32', '8', '/uploads/sale/house_img/20180815/6386333f7c27ed7983282ed5c6210381.jpg');
INSERT INTO `house_img` VALUES ('33', '8', '/uploads/sale/house_img/20180815/6988f5c967f7c44f42f5dad5a6017a97.jpg');
INSERT INTO `house_img` VALUES ('34', '8', '/uploads/sale/house_img/20180815/0eb8c5adab59e3cf0a96d6836822b852.jpg');
INSERT INTO `house_img` VALUES ('36', '10', '/uploads/sale/house_img/20180822/8cf91113b81d79934c8204c4456be1e6.jpg');
INSERT INTO `house_img` VALUES ('37', '10', '/uploads/sale/house_img/20180822/ba10d31b457c1d472d33c9d28be45496.jpg');
INSERT INTO `house_img` VALUES ('38', '10', '/uploads/sale/house_img/20180822/a574ac6ab0c86f6d8659b3874c7ce2ca.jpg');
INSERT INTO `house_img` VALUES ('42', '12', '/uploads/sale/house_img/20180823/e68dbddc3957b109ba97f9b205cff8f9.jpg');
INSERT INTO `house_img` VALUES ('43', '12', '/uploads/sale/house_img/20180823/ed10602d46d15c8a0a8c2c2004d3958b.jpg');
INSERT INTO `house_img` VALUES ('44', '13', '/uploads/sale/house_img/20180823/e67746d91e59a4a9b095e51fae067756.png');
INSERT INTO `house_img` VALUES ('45', '13', '/uploads/sale/house_img/20180823/f1a8e9bffcf8ba0d8bda784d7cb1cdae.png');
INSERT INTO `house_img` VALUES ('46', '13', '/uploads/sale/house_img/20180823/7f7f895ef4f7d368466b429f58475455.png');
INSERT INTO `house_img` VALUES ('47', '13', '/uploads/sale/house_img/20180823/16175f48055d586bce2b370ef295950b.png');
INSERT INTO `house_img` VALUES ('48', '13', '/uploads/sale/house_img/20180823/2ec54091a77d6ff046bd8c735f4b44be.png');
INSERT INTO `house_img` VALUES ('49', '14', '/uploads/sale/house_img/20180823/9ba0aec1c990022160119993fb326cd9.jpg');
INSERT INTO `house_img` VALUES ('50', '14', '/uploads/sale/house_img/20180823/1364201f86b35b0dd82df57060c4693b.jpg');
INSERT INTO `house_img` VALUES ('51', '14', '/uploads/sale/house_img/20180823/fc61fc6c880fb02796af02ab00d08373.jpg');
INSERT INTO `house_img` VALUES ('52', '14', '/uploads/sale/house_img/20180823/71f666238d66483ee52dcaa3aadba993.jpg');
INSERT INTO `house_img` VALUES ('53', '14', '/uploads/sale/house_img/20180823/adf8e93386a4ea55aec8809fc000379a.jpg');
INSERT INTO `house_img` VALUES ('54', '15', '/uploads/sale/house_img/20180824/eaf0b10e62efb8cece1627d384bf5897.png');
INSERT INTO `house_img` VALUES ('55', '15', '/uploads/sale/house_img/20180824/ca10a30763f5598322d867036a5aa86b.png');
INSERT INTO `house_img` VALUES ('56', '17', '/uploads/sale/house_img/20180824/2241c7a354769205e5aba8b63a3dddd2.jpg');
INSERT INTO `house_img` VALUES ('57', '17', '/uploads/sale/house_img/20180824/ea0003b32a360bd598f39c3780ece73c.jpg');
INSERT INTO `house_img` VALUES ('58', '17', '/uploads/sale/house_img/20180824/59d5d5cc74ee1aeae569bdcc476c5339.jpg');
INSERT INTO `house_img` VALUES ('59', '17', '/uploads/sale/house_img/20180824/ab6c2d4b8e6563b770f809365851ffca.jpg');
INSERT INTO `house_img` VALUES ('60', '17', '/uploads/sale/house_img/20180824/553c32e494b17f341c895f37adc02ef9.jpg');
INSERT INTO `house_img` VALUES ('61', '18', '/uploads/sale/house_img/20180827/b58cf34e4305b2fcd28fb9f6db95f008.png');
INSERT INTO `house_img` VALUES ('62', '18', '/uploads/sale/house_img/20180827/409f6cf9a9388a6906f0bf6cbbd19788.png');
INSERT INTO `house_img` VALUES ('63', '19', '/uploads/sale/house_img/20180827/ed81d66a5b35b2db9c5e4eba2de38233.png');
INSERT INTO `house_img` VALUES ('64', '19', '/uploads/sale/house_img/20180827/b6931ac8deb9be98ee7d9247a4051cbf.png');
INSERT INTO `house_img` VALUES ('65', '19', '/uploads/sale/house_img/20180827/2da231144acc4695dc556ab865c659e2.png');

-- ----------------------------
-- Table structure for `house_order`
-- ----------------------------
DROP TABLE IF EXISTS `house_order`;
CREATE TABLE `house_order` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(20) NOT NULL,
  `house_id` int(11) NOT NULL,
  `pay_type` varchar(20) NOT NULL,
  `order_status` tinyint(4) NOT NULL COMMENT '订单状态 1待付款 2已付款',
  `create_time` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of house_order
-- ----------------------------

-- ----------------------------
-- Table structure for `house_rent_record`
-- ----------------------------
DROP TABLE IF EXISTS `house_rent_record`;
CREATE TABLE `house_rent_record` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `house_id` int(11) NOT NULL,
  `money` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '租金金额',
  `tenancy` tinyint(4) NOT NULL COMMENT '租期 /月',
  `pay_time` int(11) NOT NULL,
  `expiry_time` int(11) NOT NULL COMMENT '到期时间',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of house_rent_record
-- ----------------------------

-- ----------------------------
-- Table structure for `house_short`
-- ----------------------------
DROP TABLE IF EXISTS `house_short`;
CREATE TABLE `house_short` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `house_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `rent` int(10) NOT NULL COMMENT '价格 /每晚',
  `decoration_mode` tinyint(4) NOT NULL DEFAULT '1' COMMENT '装修方式 1简装 2精装',
  `bedroom_number` tinyint(4) NOT NULL DEFAULT '1' COMMENT '卧室数量 /室',
  `parlour_number` tinyint(4) NOT NULL DEFAULT '1' COMMENT '客厅数量 /厅',
  `toilet_number` tinyint(4) NOT NULL DEFAULT '1' COMMENT '卫生间数量 /卫',
  `acreage` int(11) NOT NULL DEFAULT '0' COMMENT '面积 /平方米',
  `bed_number` int(11) NOT NULL DEFAULT '0' COMMENT '床数',
  `people_number` int(11) NOT NULL COMMENT '宜住人数',
  `house_type_id` int(11) NOT NULL,
  `city_id` int(11) NOT NULL,
  `area_id1` int(11) NOT NULL,
  `area_id2` int(11) NOT NULL,
  `xiaoqu_id` int(11) NOT NULL,
  `xiaoqu_name` varchar(50) NOT NULL,
  `address` varchar(255) NOT NULL,
  `is_subway` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否有地铁 1是 0否',
  `lines_id` int(11) NOT NULL DEFAULT '0' COMMENT '地铁线路id',
  `station_id` int(11) NOT NULL DEFAULT '0' COMMENT '地铁站台id',
  `tag_id` varchar(50) DEFAULT '' COMMENT '房屋标签id',
  `room_config_id` varchar(50) DEFAULT '' COMMENT '房间配置id',
  `traffic_tag_id` varchar(50) DEFAULT NULL COMMENT '交通位置标签id',
  `sale_id` int(11) NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '房源状态 1待操作  2已上架 3已下架',
  `life_id` int(11) NOT NULL COMMENT '生活体验id',
  `is_recommend` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否推荐 1是 0否',
  `create_time` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COMMENT='短租房源表';

-- ----------------------------
-- Records of house_short
-- ----------------------------
INSERT INTO `house_short` VALUES ('1', '2', '天府广场文殊院天天如家精装高级商务大床房', '公寓坐落于成都市火车北站附近人民北路一段西藏饭店旁，阳台视线无敌，晚上可观看夜景，开放式厨房可以做饭，为路途中的您带来家的味道，房间虽然不大，但是设施齐全，水电气都具备，出差或是旅游都是非常便利的小憩场所。', '328', '2', '1', '0', '1', '45', '1', '2', '4', '1', '12', '24', '2', '金开华府', '北大街2号', '1', '1', '5', '7,8,9,10,11,12', '14,15,16,17,18,19,20,21,22,23', '1,2', '10000', '2', '1', '1', '1533192293');

-- ----------------------------
-- Table structure for `house_short_img`
-- ----------------------------
DROP TABLE IF EXISTS `house_short_img`;
CREATE TABLE `house_short_img` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `short_id` int(11) NOT NULL,
  `img_url` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of house_short_img
-- ----------------------------
INSERT INTO `house_short_img` VALUES ('1', '1', '/uploads/sale/house_img/20180802/d22dde5e105d2601d7cab3f95feef820.jpg');
INSERT INTO `house_short_img` VALUES ('2', '1', '/uploads/sale/house_img/20180802/da792446a6a42d75c7e103a4ab3d8510.jpg');
INSERT INTO `house_short_img` VALUES ('3', '1', '/uploads/sale/house_img/20180802/58d69feaf2ff20347241a2ce841a3b2c.jpg');
INSERT INTO `house_short_img` VALUES ('4', '1', '/uploads/sale/house_img/20180802/b0a1dbcd1639662c09db3880e3291442.jpg');
INSERT INTO `house_short_img` VALUES ('5', '1', '/uploads/sale/house_img/20180802/f9eb18997f5fa41a5d33c29c71df66c0.jpg');

-- ----------------------------
-- Table structure for `house_tag`
-- ----------------------------
DROP TABLE IF EXISTS `house_tag`;
CREATE TABLE `house_tag` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tag_name` varchar(50) NOT NULL,
  `type` tinyint(4) NOT NULL DEFAULT '1' COMMENT '类型 1长租标签 2短租标签',
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '状态 1启用  0禁用',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=13 DEFAULT CHARSET=utf8 COMMENT='房屋标签表';

-- ----------------------------
-- Records of house_tag
-- ----------------------------
INSERT INTO `house_tag` VALUES ('1', '押一付一', '1', '1');
INSERT INTO `house_tag` VALUES ('2', '认证公寓', '1', '1');
INSERT INTO `house_tag` VALUES ('3', '近地铁', '1', '1');
INSERT INTO `house_tag` VALUES ('4', '精装修', '1', '1');
INSERT INTO `house_tag` VALUES ('5', '随时看房', '1', '1');
INSERT INTO `house_tag` VALUES ('6', '新上', '1', '1');
INSERT INTO `house_tag` VALUES ('7', '可做饭', '2', '1');
INSERT INTO `house_tag` VALUES ('8', '允许聚会', '2', '1');
INSERT INTO `house_tag` VALUES ('9', '欢迎宠物', '2', '1');
INSERT INTO `house_tag` VALUES ('10', '接待外宾', '2', '1');
INSERT INTO `house_tag` VALUES ('11', '接送机站', '2', '1');
INSERT INTO `house_tag` VALUES ('12', '导游服务', '2', '1');

-- ----------------------------
-- Table structure for `house_type`
-- ----------------------------
DROP TABLE IF EXISTS `house_type`;
CREATE TABLE `house_type` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(20) NOT NULL,
  `type` tinyint(4) NOT NULL DEFAULT '1' COMMENT '类型 1长租房屋类型 2短租房屋类型',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8 COMMENT='房屋类型表';

-- ----------------------------
-- Records of house_type
-- ----------------------------
INSERT INTO `house_type` VALUES ('1', '公寓', '1');
INSERT INTO `house_type` VALUES ('2', '普通住宅', '1');
INSERT INTO `house_type` VALUES ('3', '民居', '2');
INSERT INTO `house_type` VALUES ('4', '公寓', '2');
INSERT INTO `house_type` VALUES ('5', '客栈', '2');
INSERT INTO `house_type` VALUES ('6', '别墅', '2');
INSERT INTO `house_type` VALUES ('7', '海景房', '2');
INSERT INTO `house_type` VALUES ('8', '老洋房', '2');

-- ----------------------------
-- Table structure for `house_xiaoqu`
-- ----------------------------
DROP TABLE IF EXISTS `house_xiaoqu`;
CREATE TABLE `house_xiaoqu` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `city_id` int(11) NOT NULL COMMENT '城市id',
  `area_id1` int(11) NOT NULL DEFAULT '0' COMMENT '一级区域id',
  `area_id2` int(11) NOT NULL DEFAULT '0' COMMENT '二级区域id',
  `xiaoqu_name` varchar(50) NOT NULL,
  `address` varchar(255) NOT NULL,
  `lng` varchar(20) NOT NULL COMMENT '经度',
  `lat` varchar(20) NOT NULL COMMENT '纬度',
  `shop_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8 COMMENT='小区信息表';

-- ----------------------------
-- Records of house_xiaoqu
-- ----------------------------
INSERT INTO `house_xiaoqu` VALUES ('1', '1', '12', '24', '罗马国际', '青龙街18号', '104.064709', '30.669087', '1');
INSERT INTO `house_xiaoqu` VALUES ('2', '1', '12', '24', '金开华府 ', '北大街2号', '104.075775', '30.673193', '1');
INSERT INTO `house_xiaoqu` VALUES ('3', '1', '12', '24', '北打铜街4号院', '北打铜街4号', '104.072001', '30.669531', '1');
INSERT INTO `house_xiaoqu` VALUES ('4', '1', '12', '24', '星乐家园', '华府大道238号', '104.072001', '30.669531', '1');
INSERT INTO `house_xiaoqu` VALUES ('7', '1', '54', '55', '兴城嘉苑三期', '云顶山路', '104.134712', '30.623327', '2');

-- ----------------------------
-- Table structure for `long_order`
-- ----------------------------
DROP TABLE IF EXISTS `long_order`;
CREATE TABLE `long_order` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(50) NOT NULL,
  `user_id` int(11) DEFAULT '0',
  `house_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `property_id` int(11) DEFAULT '0' COMMENT '物业id',
  `username` varchar(50) NOT NULL COMMENT '联系人',
  `mobile` char(11) NOT NULL COMMENT '联系人电话',
  `people_number` int(11) DEFAULT '0' COMMENT '入住人数',
  `reserve_money` decimal(10,2) DEFAULT '0.00' COMMENT '定金 支付金额',
  `deposit_money` decimal(10,2) DEFAULT '0.00' COMMENT '押金',
  `rent` decimal(10,2) DEFAULT '0.00' COMMENT '月租金',
  `pay_type` varchar(20) DEFAULT '' COMMENT '支付方式',
  `pay_time` int(11) DEFAULT '0',
  `cancel_time` int(11) DEFAULT NULL,
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1待支付 2已支付 -1已取消',
  `renting_status` tinyint(4) DEFAULT '0' COMMENT '租房状态 1在租 2完结',
  `refund_deposit` decimal(10,2) DEFAULT NULL COMMENT '退款押金',
  `refund_rent` decimal(10,2) DEFAULT NULL COMMENT '已退租金',
  `is_delete` tinyint(4) NOT NULL DEFAULT '0' COMMENT '标记删除 1已删除',
  `create_time` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8 COMMENT='长租预租房订单';

-- ----------------------------
-- Records of long_order
-- ----------------------------
INSERT INTO `long_order` VALUES ('7', 'L20180813916371', '10011', '2', '10000', '1', '夏天', '15881050779', '0', '750.00', '1500.00', '1500.00', '支付宝', '1534168877', null, '2', '2', '1500.00', null, '0', '1534148877');
INSERT INTO `long_order` VALUES ('8', 'L20180816530693', '0', '3', '10000', '1', '吴明', '13512345678', '0', '0.00', '0.00', '0.00', '', '0', null, '2', '2', '10.00', null, '0', '1534386913');
INSERT INTO `long_order` VALUES ('9', 'L20180818740392', '10012', '4', '10000', '10001', '张九日', '15881050771', '0', '1100.00', '2200.00', '2200.00', '支付宝', '1534198877', null, '2', '1', null, null, '0', '1534396913');
INSERT INTO `long_order` VALUES ('10', 'L20180818740393', '10012', '5', '10000', '10001', '张九日', '15881050771', '0', '1200.00', '0.00', '2400.00', '支付宝', '1534198877', null, '2', '0', null, null, '0', '1534396913');
INSERT INTO `long_order` VALUES ('11', 'L20180830123294', '10011', '2', '10000', '0', '张新生', '15345643221', '0', '750.00', '0.00', '0.00', '', '0', null, '1', '0', null, null, '0', '1535619599');
INSERT INTO `long_order` VALUES ('12', 'L20180830502224', '10011', '2', '10000', '0', '张新生', '15345643221', '0', '750.00', '0.00', '0.00', '', '0', null, '1', '0', null, null, '0', '1535619639');
INSERT INTO `long_order` VALUES ('14', 'L20180830910905', '10011', '2', '10000', '0', '张新生', '15345643221', '0', '750.00', '0.00', '0.00', '', '0', null, '1', '0', null, null, '0', '1535621937');
INSERT INTO `long_order` VALUES ('15', 'L20180913788371', '10013', '2', '10000', '0', '杨东', '15252525252', '2147483647', '0.01', '0.00', '0.00', '微信', '0', null, '1', '0', null, null, '0', '1536806611');
INSERT INTO `long_order` VALUES ('16', 'L20180913919838', '10013', '2', '10000', '1', '杨东', '15252525252', '2147483647', '0.01', '0.10', '0.10', '支付宝', '0', null, '2', '2', '0.10', null, '0', '1536806964');
INSERT INTO `long_order` VALUES ('18', 'L20181009625889', '10014', '5', '10000', '0', '小王', '18200000000', '2', '1200.00', '0.00', '0.00', '支付宝', '0', null, '1', '0', null, null, '0', '1539050114');

-- ----------------------------
-- Table structure for `long_rent_record`
-- ----------------------------
DROP TABLE IF EXISTS `long_rent_record`;
CREATE TABLE `long_rent_record` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `money` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '缴纳金额',
  `start_time` date NOT NULL,
  `end_time` date NOT NULL,
  `create_time` int(11) NOT NULL COMMENT '缴纳租金时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8 COMMENT='租金缴纳记录表';

-- ----------------------------
-- Records of long_rent_record
-- ----------------------------
INSERT INTO `long_rent_record` VALUES ('5', '7', '1500.00', '2018-08-15', '2018-09-15', '1534341365');
INSERT INTO `long_rent_record` VALUES ('6', '7', '4500.00', '2018-09-15', '2018-12-15', '1536976605');
INSERT INTO `long_rent_record` VALUES ('7', '8', '3600.00', '2018-08-16', '2018-10-16', '1534386913');
INSERT INTO `long_rent_record` VALUES ('8', '9', '13200.00', '2018-08-24', '2019-02-24', '1535093347');
INSERT INTO `long_rent_record` VALUES ('9', '9', '6600.00', '2019-02-25', '2019-05-25', '1535097306');
INSERT INTO `long_rent_record` VALUES ('10', '16', '0.10', '2018-09-15', '2018-09-18', '1536914907');
INSERT INTO `long_rent_record` VALUES ('11', '9', '6600.00', '2019-05-25', '2019-08-25', '1537170497');

-- ----------------------------
-- Table structure for `messages`
-- ----------------------------
DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `create_time` int(11) NOT NULL,
  `ip` varchar(50) NOT NULL,
  `is_look` int(1) NOT NULL DEFAULT '0' COMMENT '0未读1已读',
  `message` text NOT NULL,
  `update_time` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id` (`id`) USING BTREE,
  KEY `is_look` (`is_look`) USING BTREE,
  KEY `create_time` (`create_time`) USING BTREE
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of messages
-- ----------------------------

-- ----------------------------
-- Table structure for `property`
-- ----------------------------
DROP TABLE IF EXISTS `property`;
CREATE TABLE `property` (
  `property_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `token` varchar(40) DEFAULT '',
  `token_expire_time` int(11) NOT NULL,
  `xiaoqu_id` int(11) NOT NULL COMMENT '小区id',
  `mobile` char(11) NOT NULL,
  `nickname` varchar(50) DEFAULT '',
  `password` varchar(255) NOT NULL,
  `pay_password` varchar(255) DEFAULT NULL COMMENT '支付密码',
  `money` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '账户金额',
  `avatar` varchar(255) DEFAULT '',
  `login_time` int(11) NOT NULL,
  `property_status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '状态 1正常 0禁用',
  `create_time` int(11) NOT NULL,
  PRIMARY KEY (`property_id`)
) ENGINE=MyISAM AUTO_INCREMENT=10007 DEFAULT CHARSET=utf8 COMMENT='物业人员表';

-- ----------------------------
-- Records of property
-- ----------------------------
INSERT INTO `property` VALUES ('10002', 'd5daf8c39ca75ebc653aa0033432e6fdc07902d2', '1537932166', '2', '18202815998', '王wang', '$2y$10$ATP2fdYIFwxaGTONYYqEWuMyAlEbJqOQZv4/pjQJk3l5NSSXyRkcK', '$2y$10$9HJe9mQz8Blfpd3wEbDcIe/bn7Fqg5jmhqaGzejqcBvytKFG324mG', '79.90', '/uploads/property/property_avatar/20180822/79de5e6488b6f4cfd72425bdff459d30.png', '1537327366', '1', '1532489744');
INSERT INTO `property` VALUES ('10001', 'bc0d720b41860f685fc82e690de9deb0e1058c62', '1535446168', '1', '18380448164', '杨梅', '$2y$10$ATP2fdYIFwxaGTONYYqEWuMyAlEbJqOQZv4/pjQJk3l5NSSXyRkcK', null, '30.00', '\\uploads\\property\\avatar\\20181012\\81fffa4cafc9da8629ea86892b31bffc.jpg', '0', '1', '1532489744');
INSERT INTO `property` VALUES ('10006', '', '0', '1', '15880809090', '小海', '$2y$10$eSq63CohtxD9sgSLfVnTn.iTDru4UugvobV0BhxabwUEIr/pVn1/m', null, '0.00', '', '0', '1', '1539326369');

-- ----------------------------
-- Table structure for `property_money_record`
-- ----------------------------
DROP TABLE IF EXISTS `property_money_record`;
CREATE TABLE `property_money_record` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `property_id` int(11) NOT NULL,
  `house_id` int(11) NOT NULL,
  `type` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1有效推荐 2有效看房',
  `money` decimal(10,2) NOT NULL,
  `create_time` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8 COMMENT='物业金额记录';

-- ----------------------------
-- Records of property_money_record
-- ----------------------------
INSERT INTO `property_money_record` VALUES ('1', '10002', '2', '1', '50.00', '1534250436');
INSERT INTO `property_money_record` VALUES ('2', '10002', '2', '2', '30.00', '1534252436');
INSERT INTO `property_money_record` VALUES ('3', '10001', '4', '2', '30.00', '1535093347');
INSERT INTO `property_money_record` VALUES ('4', '10002', '2', '2', '30.00', '1536914907');

-- ----------------------------
-- Table structure for `property_tixian_record`
-- ----------------------------
DROP TABLE IF EXISTS `property_tixian_record`;
CREATE TABLE `property_tixian_record` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `money` decimal(10,2) NOT NULL,
  `order_no` varchar(255) NOT NULL,
  `property_id` int(11) NOT NULL,
  `alipay_account` varchar(100) NOT NULL,
  `code` varchar(10) DEFAULT NULL,
  `order_id` varchar(64) DEFAULT NULL,
  `create_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of property_tixian_record
-- ----------------------------
INSERT INTO `property_tixian_record` VALUES ('1', '0.10', 'T20180829582455', '10002', '15881050779', '10000', '20180829110070001502860018088781', '2018-08-29 16:31:28');
INSERT INTO `property_tixian_record` VALUES ('2', '79.90', 'T20180917408617', '10002', '18202815998', '40004', null, '2018-09-17 17:08:04');

-- ----------------------------
-- Table structure for `province`
-- ----------------------------
DROP TABLE IF EXISTS `province`;
CREATE TABLE `province` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `province_name` varchar(20) NOT NULL,
  `paixu` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8 COMMENT='省份表';

-- ----------------------------
-- Records of province
-- ----------------------------
INSERT INTO `province` VALUES ('1', '四川', '0');
INSERT INTO `province` VALUES ('2', '北京', '0');
INSERT INTO `province` VALUES ('3', '上海', '0');
INSERT INTO `province` VALUES ('4', '云南', '0');
INSERT INTO `province` VALUES ('5', '广东', '0');
INSERT INTO `province` VALUES ('8', '山东', '0');
INSERT INTO `province` VALUES ('9', '重庆', '0');
INSERT INTO `province` VALUES ('10', '陕西', '0');
INSERT INTO `province` VALUES ('11', '江苏', '0');

-- ----------------------------
-- Table structure for `rent_record`
-- ----------------------------
DROP TABLE IF EXISTS `rent_record`;
CREATE TABLE `rent_record` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `money` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '缴纳金额',
  `start_time` date NOT NULL,
  `end_time` date NOT NULL,
  `create_time` int(11) NOT NULL COMMENT '缴纳租金时间',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COMMENT='租金缴纳记录表';

-- ----------------------------
-- Records of rent_record
-- ----------------------------
INSERT INTO `rent_record` VALUES ('1', '7', '1500.00', '2018-08-14', '2018-09-14', '1534148877');
INSERT INTO `rent_record` VALUES ('2', '7', '3000.00', '2018-09-14', '2018-10-14', '1534158877');

-- ----------------------------
-- Table structure for `room_config`
-- ----------------------------
DROP TABLE IF EXISTS `room_config`;
CREATE TABLE `room_config` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(20) NOT NULL,
  `icon` varchar(255) NOT NULL,
  `type` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1长租房配置 2短租房配置',
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '状态 1启用 0禁用',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=24 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of room_config
-- ----------------------------
INSERT INTO `room_config` VALUES ('1', '床', '', '1', '1');
INSERT INTO `room_config` VALUES ('2', '宽带', '', '1', '1');
INSERT INTO `room_config` VALUES ('3', '电视', '', '1', '1');
INSERT INTO `room_config` VALUES ('4', '洗衣机', '', '1', '1');
INSERT INTO `room_config` VALUES ('5', '暖气', '', '1', '1');
INSERT INTO `room_config` VALUES ('6', '空调', '', '1', '1');
INSERT INTO `room_config` VALUES ('7', '冰箱', '', '1', '1');
INSERT INTO `room_config` VALUES ('8', '热水器', '', '1', '1');
INSERT INTO `room_config` VALUES ('9', '衣柜', '', '1', '1');
INSERT INTO `room_config` VALUES ('10', '天台', '', '1', '1');
INSERT INTO `room_config` VALUES ('11', '沙发', '', '1', '1');
INSERT INTO `room_config` VALUES ('12', '可做饭', '', '1', '1');
INSERT INTO `room_config` VALUES ('13', '卫生间', '', '1', '1');
INSERT INTO `room_config` VALUES ('14', '冰箱', '', '2', '1');
INSERT INTO `room_config` VALUES ('15', '洗衣机', '', '2', '1');
INSERT INTO `room_config` VALUES ('16', '电脑', '', '2', '1');
INSERT INTO `room_config` VALUES ('17', '空调', '', '2', '1');
INSERT INTO `room_config` VALUES ('18', '暖气', '', '2', '1');
INSERT INTO `room_config` VALUES ('19', '电梯', '', '2', '1');
INSERT INTO `room_config` VALUES ('20', '停车位', '', '2', '1');
INSERT INTO `room_config` VALUES ('21', 'wifi', '', '2', '1');
INSERT INTO `room_config` VALUES ('22', '电视', '', '2', '1');
INSERT INTO `room_config` VALUES ('23', '沐浴', '', '2', '1');

-- ----------------------------
-- Table structure for `sale`
-- ----------------------------
DROP TABLE IF EXISTS `sale`;
CREATE TABLE `sale` (
  `sale_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `token` varchar(40) DEFAULT NULL,
  `token_expire_time` int(11) NOT NULL,
  `mobile` char(11) NOT NULL,
  `nickname` varchar(50) DEFAULT '',
  `password` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT '',
  `login_time` int(11) NOT NULL,
  `sale_status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '销售员状态 1正常 0禁用',
  `shop_id` int(11) NOT NULL,
  `create_time` int(11) NOT NULL,
  PRIMARY KEY (`sale_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10002 DEFAULT CHARSET=utf8 COMMENT='销售人员表';

-- ----------------------------
-- Records of sale
-- ----------------------------
INSERT INTO `sale` VALUES ('10000', '9b8f175ca653618843d35a2bf60682dfbf180213', '1539924356', '18380448164', '马飞飞', '$2y$10$ATP2fdYIFwxaGTONYYqEWuMyAlEbJqOQZv4/pjQJk3l5NSSXyRkcK', '/uploads/sale/sale_avatar/20180827/169c00131782a1afec440da674dc34b8.jpg', '1539224492', '1', '1', '1532489744');
INSERT INTO `sale` VALUES ('10001', '23e798117b32e5305761152b0d1c54335d63341d', '1539844359', '15890909090', '何小mei', '$2y$10$.cIz8nm.FmpHhCv3wFlXSet0nkBdp3Zh5RV8z423YzF4MkY4GWTji', '/uploads/sale/sale_avatar/20180820/2bae5476c73ed2762877fbd6e9ee9204.png', '1538990292', '1', '1', '1532489744');

-- ----------------------------
-- Table structure for `shopping_cart`
-- ----------------------------
DROP TABLE IF EXISTS `shopping_cart`;
CREATE TABLE `shopping_cart` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `goods_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `number` int(11) NOT NULL,
  `create_time` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=27 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of shopping_cart
-- ----------------------------
INSERT INTO `shopping_cart` VALUES ('6', '3', '10012', '5', '1533524267');
INSERT INTO `shopping_cart` VALUES ('26', '3', '10013', '1', '1539227617');
INSERT INTO `shopping_cart` VALUES ('25', '3', '10014', '1', '1538991473');
INSERT INTO `shopping_cart` VALUES ('24', '2', '10014', '1', '1538991469');

-- ----------------------------
-- Table structure for `shop_info`
-- ----------------------------
DROP TABLE IF EXISTS `shop_info`;
CREATE TABLE `shop_info` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `shop_name` varchar(100) NOT NULL,
  `address` varchar(255) NOT NULL,
  `shopkeeper` varchar(20) NOT NULL COMMENT '店长',
  `mobile` char(11) NOT NULL,
  `lng` varchar(20) NOT NULL,
  `lat` varchar(20) NOT NULL,
  `create_time` int(11) NOT NULL,
  `city_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of shop_info
-- ----------------------------
INSERT INTO `shop_info` VALUES ('1', '青羊区分店', '四川省成都市青羊区西玉龙街108号', '刘德华', '13588889999', '104.068553', '30.666849', '1533111528', '1');
INSERT INTO `shop_info` VALUES ('2', '高新区分店', '四川省成都市高新区天府三街69号', '张学友', '15112346543', '104.065977', '30.546601', '1533111528', '1');

-- ----------------------------
-- Table structure for `short_collection`
-- ----------------------------
DROP TABLE IF EXISTS `short_collection`;
CREATE TABLE `short_collection` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `short_id` int(11) NOT NULL,
  `create_time` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of short_collection
-- ----------------------------
INSERT INTO `short_collection` VALUES ('1', '10011', '1', '1534296529');
INSERT INTO `short_collection` VALUES ('2', '10014', '1', '1539075415');

-- ----------------------------
-- Table structure for `short_comment`
-- ----------------------------
DROP TABLE IF EXISTS `short_comment`;
CREATE TABLE `short_comment` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `short_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` varchar(255) NOT NULL,
  `hygiene_score` float(2,1) NOT NULL DEFAULT '5.0' COMMENT '卫生评分',
  `service_score` float(2,1) NOT NULL DEFAULT '5.0' COMMENT '服务评分',
  `position_score` float(2,1) NOT NULL DEFAULT '5.0' COMMENT '位置评分',
  `renovation_score` float(2,1) NOT NULL DEFAULT '5.0' COMMENT '室内评分',
  `create_time` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of short_comment
-- ----------------------------
INSERT INTO `short_comment` VALUES ('1', '1', '10011', '很不错，环境很舒适，晚上窗外可以观夜景！', '5.0', '5.0', '5.0', '5.0', '1534229982');
INSERT INTO `short_comment` VALUES ('2', '1', '10012', '还行吧，服务态度有点差', '4.5', '2.0', '4.5', '4.5', '1534239982');

-- ----------------------------
-- Table structure for `short_life`
-- ----------------------------
DROP TABLE IF EXISTS `short_life`;
CREATE TABLE `short_life` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COMMENT='生活体验标签';

-- ----------------------------
-- Records of short_life
-- ----------------------------
INSERT INTO `short_life` VALUES ('1', '冲浪');
INSERT INTO `short_life` VALUES ('2', '运动');
INSERT INTO `short_life` VALUES ('3', '餐饮');
INSERT INTO `short_life` VALUES ('4', '娱乐');
INSERT INTO `short_life` VALUES ('5', '音乐');

-- ----------------------------
-- Table structure for `short_occupant`
-- ----------------------------
DROP TABLE IF EXISTS `short_occupant`;
CREATE TABLE `short_occupant` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` tinyint(4) NOT NULL DEFAULT '1' COMMENT '类型 1身份证',
  `realname` varchar(20) NOT NULL,
  `id_card` varchar(20) NOT NULL COMMENT '身份证号码',
  `create_time` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8 COMMENT='短租 入住人信息表';

-- ----------------------------
-- Records of short_occupant
-- ----------------------------
INSERT INTO `short_occupant` VALUES ('1', '10011', '1', '张三', '513029197804123666', '1532576700');
INSERT INTO `short_occupant` VALUES ('2', '10013', '1', '王端', '510824199808088888', '0');
INSERT INTO `short_occupant` VALUES ('3', '10014', '1', '李易峰', '511002199105067827', '0');
INSERT INTO `short_occupant` VALUES ('4', '10014', '1', '王甜甜', '278378373737373737', '0');

-- ----------------------------
-- Table structure for `short_order`
-- ----------------------------
DROP TABLE IF EXISTS `short_order`;
CREATE TABLE `short_order` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `short_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `room_number` varchar(20) DEFAULT '1' COMMENT '房间数量',
  `people_number` tinyint(4) NOT NULL COMMENT '人数',
  `start_time` date NOT NULL COMMENT '开始入住时间',
  `end_time` date NOT NULL COMMENT '最后入住时间',
  `username` varchar(20) NOT NULL,
  `mobile` char(11) NOT NULL,
  `occupant_id` varchar(20) NOT NULL COMMENT '入住人id',
  `pay_money` decimal(10,2) NOT NULL,
  `pay_type` varchar(20) NOT NULL,
  `pay_time` int(11) NOT NULL,
  `cancel_time` int(11) NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '订单状态 1待支付 2已支付 3出租中  4待评价  5已完成 -1待支付已取消 -2已支付已取消',
  `deposit_money` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '押金',
  `refund_deposit` decimal(10,2) DEFAULT NULL COMMENT '退回押金金额',
  `is_delete` tinyint(4) NOT NULL,
  `create_time` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8 COMMENT='短租订单表';

-- ----------------------------
-- Records of short_order
-- ----------------------------
INSERT INTO `short_order` VALUES ('1', 'L20180814656252', '10011', '1', '10000', '1', '1', '2018-09-11', '2018-09-13', '孟先生', '15889897878', '1', '656.00', '支付宝', '1534250436', '0', '4', '200.00', '200.00', '0', '1534250436');
INSERT INTO `short_order` VALUES ('4', 'L20180907325567', '10013', '1', '10000', '1', '2', '2018-09-14', '2018-09-15', '王栋', '18888888888', '2,0', '328.00', '支付宝', '1534250436', '0', '2', '100.00', null, '0', '1536283994');
INSERT INTO `short_order` VALUES ('5', 'L20180907325565', '10011', '1', '10000', '1', '1', '2018-09-18', '2018-09-19', '王大锤', '18888888888', '1', '328.00', '支付宝', '1534250436', '0', '2', '100.00', null, '0', '0');
INSERT INTO `short_order` VALUES ('6', 'L20181010526776', '10014', '1', '10000', '1', '2', '2018-10-10', '2018-10-11', '', '18200000000', '3,4', '328.00', '', '0', '0', '1', '0.00', null, '0', '1539154265');

-- ----------------------------
-- Table structure for `short_rule`
-- ----------------------------
DROP TABLE IF EXISTS `short_rule`;
CREATE TABLE `short_rule` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `fksz` varchar(255) DEFAULT NULL,
  `yfff` varchar(20) DEFAULT NULL,
  `xxsyj` varchar(20) DEFAULT NULL,
  `ewfy` varchar(255) DEFAULT NULL,
  `bdgh` varchar(20) DEFAULT NULL,
  `jdsj` varchar(50) DEFAULT NULL,
  `zsrz` varchar(20) DEFAULT NULL,
  `rzsj` varchar(20) DEFAULT NULL,
  `tfsj` varchar(20) DEFAULT NULL,
  `zdrzts` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of short_rule
-- ----------------------------
INSERT INTO `short_rule` VALUES ('1', '吸烟:不允许吸烟', '100', '300', '暂无', '每客一换', '09:00-24:00', '3天起租', '14:00以后', '12:00以前', '180天');

-- ----------------------------
-- Table structure for `short_traffic_tag`
-- ----------------------------
DROP TABLE IF EXISTS `short_traffic_tag`;
CREATE TABLE `short_traffic_tag` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `status` tinyint(4) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of short_traffic_tag
-- ----------------------------
INSERT INTO `short_traffic_tag` VALUES ('1', '近地铁', '1');
INSERT INTO `short_traffic_tag` VALUES ('2', '近火车站', '1');
INSERT INTO `short_traffic_tag` VALUES ('3', '近飞机场', '1');

-- ----------------------------
-- Table structure for `smsconfig`
-- ----------------------------
DROP TABLE IF EXISTS `smsconfig`;
CREATE TABLE `smsconfig` (
  `sms` varchar(10) NOT NULL DEFAULT 'sms' COMMENT '标识',
  `appkey` varchar(200) NOT NULL,
  `secretkey` varchar(200) NOT NULL,
  `type` varchar(100) DEFAULT 'normal' COMMENT '短信类型',
  `name` varchar(100) NOT NULL COMMENT '短信签名',
  `code` varchar(100) NOT NULL COMMENT '短信模板ID',
  `content` text NOT NULL COMMENT '短信默认模板',
  KEY `sms` (`sms`) USING BTREE
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of smsconfig
-- ----------------------------
INSERT INTO `smsconfig` VALUES ('sms', '', '', '', '', '', '');

-- ----------------------------
-- Table structure for `subway_lines`
-- ----------------------------
DROP TABLE IF EXISTS `subway_lines`;
CREATE TABLE `subway_lines` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `city_id` int(11) NOT NULL,
  `lines_name` varchar(50) NOT NULL,
  `paixu` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of subway_lines
-- ----------------------------
INSERT INTO `subway_lines` VALUES ('1', '1', '1号线', '1');
INSERT INTO `subway_lines` VALUES ('2', '1', '2号线', '2');
INSERT INTO `subway_lines` VALUES ('3', '1', '3号线', '3');
INSERT INTO `subway_lines` VALUES ('4', '1', '4号线', '4');
INSERT INTO `subway_lines` VALUES ('5', '1', '7号线', '5');
INSERT INTO `subway_lines` VALUES ('6', '1', '10号线', '6');
INSERT INTO `subway_lines` VALUES ('8', '1', '6号线', '0');
INSERT INTO `subway_lines` VALUES ('11', '6', '1号线', '0');

-- ----------------------------
-- Table structure for `subway_station`
-- ----------------------------
DROP TABLE IF EXISTS `subway_station`;
CREATE TABLE `subway_station` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `lines_id` int(11) NOT NULL,
  `station_name` varchar(50) NOT NULL,
  `paixu` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=58 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of subway_station
-- ----------------------------
INSERT INTO `subway_station` VALUES ('1', '1', '韦家碾', '0');
INSERT INTO `subway_station` VALUES ('2', '1', '升仙湖', '0');
INSERT INTO `subway_station` VALUES ('3', '1', '火车北站', '0');
INSERT INTO `subway_station` VALUES ('4', '1', '人民北路', '0');
INSERT INTO `subway_station` VALUES ('5', '1', '文殊院', '0');
INSERT INTO `subway_station` VALUES ('6', '1', '骡马市', '0');
INSERT INTO `subway_station` VALUES ('7', '1', '天府广场', '0');
INSERT INTO `subway_station` VALUES ('8', '1', '锦江宾馆', '0');
INSERT INTO `subway_station` VALUES ('9', '1', '华西坝', '0');
INSERT INTO `subway_station` VALUES ('10', '1', '省体育馆', '0');
INSERT INTO `subway_station` VALUES ('11', '1', '倪家桥', '0');
INSERT INTO `subway_station` VALUES ('12', '1', '桐梓林', '0');
INSERT INTO `subway_station` VALUES ('13', '1', '火车南站', '0');
INSERT INTO `subway_station` VALUES ('14', '1', '高新', '0');
INSERT INTO `subway_station` VALUES ('15', '1', '金融城', '0');
INSERT INTO `subway_station` VALUES ('16', '1', '孵化园', '0');
INSERT INTO `subway_station` VALUES ('17', '1', '锦城广场', '0');
INSERT INTO `subway_station` VALUES ('18', '1', '世纪城', '0');
INSERT INTO `subway_station` VALUES ('19', '1', '天府三街', '0');
INSERT INTO `subway_station` VALUES ('20', '1', '天府五街', '0');
INSERT INTO `subway_station` VALUES ('21', '1', '华府大道', '0');
INSERT INTO `subway_station` VALUES ('22', '1', '四河', '0');
INSERT INTO `subway_station` VALUES ('23', '1', '华阳', '0');
INSERT INTO `subway_station` VALUES ('24', '2', '犀浦', '0');
INSERT INTO `subway_station` VALUES ('25', '2', '天河路', '0');
INSERT INTO `subway_station` VALUES ('26', '2', '百草路', '0');
INSERT INTO `subway_station` VALUES ('27', '2', '金周路', '0');
INSERT INTO `subway_station` VALUES ('28', '2', '金科北路', '0');
INSERT INTO `subway_station` VALUES ('29', '2', '迎宾大道', '0');
INSERT INTO `subway_station` VALUES ('30', '2', '茶店子客运站', '0');
INSERT INTO `subway_station` VALUES ('31', '2', '羊犀立交', '0');
INSERT INTO `subway_station` VALUES ('32', '2', '一品天下', '0');
INSERT INTO `subway_station` VALUES ('33', '2', '蜀汉路东', '0');
INSERT INTO `subway_station` VALUES ('34', '2', '白果林', '0');
INSERT INTO `subway_station` VALUES ('35', '2', '中医大省医院', '0');
INSERT INTO `subway_station` VALUES ('36', '2', '通惠门', '0');
INSERT INTO `subway_station` VALUES ('37', '2', '人民公园', '0');
INSERT INTO `subway_station` VALUES ('38', '2', '天府广场', '0');
INSERT INTO `subway_station` VALUES ('39', '2', '春熙路', '0');
INSERT INTO `subway_station` VALUES ('40', '2', '东门大桥', '0');
INSERT INTO `subway_station` VALUES ('41', '2', '牛王庙', '0');
INSERT INTO `subway_station` VALUES ('42', '2', '牛市口', '0');
INSERT INTO `subway_station` VALUES ('43', '2', '东大路', '0');
INSERT INTO `subway_station` VALUES ('44', '2', '塔子山公园', '0');
INSERT INTO `subway_station` VALUES ('45', '2', '成都东客站', '0');
INSERT INTO `subway_station` VALUES ('46', '2', '成渝立交', '0');
INSERT INTO `subway_station` VALUES ('47', '2', '惠王陵', '0');
INSERT INTO `subway_station` VALUES ('48', '2', '洪河', '0');
INSERT INTO `subway_station` VALUES ('49', '2', '成都行政学院', '0');
INSERT INTO `subway_station` VALUES ('50', '2', '大面铺', '0');
INSERT INTO `subway_station` VALUES ('51', '2', '连山坡', '0');
INSERT INTO `subway_station` VALUES ('52', '2', '界牌', '0');
INSERT INTO `subway_station` VALUES ('53', '2', '书房', '0');
INSERT INTO `subway_station` VALUES ('54', '2', '龙平路', '0');
INSERT INTO `subway_station` VALUES ('55', '2', '龙泉驿', '0');
INSERT INTO `subway_station` VALUES ('56', '8', '测试', '0');
INSERT INTO `subway_station` VALUES ('57', '11', '测试站台', '0');

-- ----------------------------
-- Table structure for `urlconfig`
-- ----------------------------
DROP TABLE IF EXISTS `urlconfig`;
CREATE TABLE `urlconfig` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aliases` varchar(200) NOT NULL COMMENT '想要设置的别名',
  `url` varchar(200) NOT NULL COMMENT '原url结构',
  `desc` text COMMENT '备注',
  `status` int(1) NOT NULL DEFAULT '1' COMMENT '0禁用1使用',
  `create_time` int(11) NOT NULL,
  `update_time` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id` (`id`) USING BTREE,
  KEY `status` (`status`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of urlconfig
-- ----------------------------
INSERT INTO `urlconfig` VALUES ('1', 'admin_login', 'admin/common/login', '后台登录地址。', '0', '1517621629', '1517621629');

-- ----------------------------
-- Table structure for `user`
-- ----------------------------
DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
  `user_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `token` varchar(40) DEFAULT '',
  `token_expire_time` int(11) NOT NULL,
  `mobile` char(11) NOT NULL,
  `nickname` varchar(50) DEFAULT '',
  `password` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT '' COMMENT '/uploads/user/user_avatar/defalut_avatar.png',
  `login_time` int(11) NOT NULL,
  `user_status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '用户状态 1正常 0禁用',
  `create_time` int(11) NOT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=10017 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of user
-- ----------------------------
INSERT INTO `user` VALUES ('10011', '107502c8faf961ed047e326abeb3ce071a892da9', '1538642909', '15881050779', '被风吹过的夏天', '$2y$10$Cz9lZEj.tKXKaZfI88zRzucotuR9An6A7VoAX3Q.7HfJOLHQZ5RNG', '/uploads/user/user_avatar/20180725/cd1b72c905c74f2803cd50ab46c3670a.jpg', '1538038047', '1', '1532489744');
INSERT INTO `user` VALUES ('10012', 'fb87e7cd98f19611373c8c0fb1f2722a091d0d1c', '1534755036', '15881050771', '新用户158****0771', '$2y$10$mvO2RadDyjNVkiEXaETSBOfG2vxqp4iKGtwSls7bVF4waUewUV0mS', '/uploads/user/user_avatar/20180725/36a3aa6f4b5e103b50d6822e2effdffb.jpg', '1532500952', '1', '1532490991');
INSERT INTO `user` VALUES ('10013', 'f73546e23468d8b9386436ff469c3665e806ad45', '1539843985', '18380448164', '新用户183****8164', '$2y$10$BFGQbHbgxRONY46g02DSV.bH34dSNOaI3T6F7K0QLPeZdFqjfvJoG', '\\uploads\\user\\avatar\\20180925\\d6f64affb50e6d11253f03ec63becd01.jpg', '1539139116', '1', '1535426539');
INSERT INTO `user` VALUES ('10014', '06cbf209235ce9f72ae4939787efd8e3a247c02f', '1540177611', '18202815998', '新用户182****5998', '$2y$10$38bnGEXkVz6d2A3gHxUQRez4BbA4lsltWDN8Z4a7F2n87sH8RNooe', '/uploads/user/user_avatar/20181008/31e6529e9c2b641bfc86eca4f3d6b97a.png', '1539243764', '1', '1537259544');

-- ----------------------------
-- Table structure for `user_address`
-- ----------------------------
DROP TABLE IF EXISTS `user_address`;
CREATE TABLE `user_address` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) NOT NULL,
  `username` varchar(20) NOT NULL,
  `mobile` varchar(20) NOT NULL,
  `province` varchar(20) NOT NULL,
  `city` varchar(20) NOT NULL,
  `area` varchar(20) NOT NULL,
  `address` varchar(255) NOT NULL,
  `is_default` tinyint(4) NOT NULL,
  `create_time` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=18 DEFAULT CHARSET=utf8 COMMENT='用户收货地址表';

-- ----------------------------
-- Records of user_address
-- ----------------------------
INSERT INTO `user_address` VALUES ('10', '10011', '张甜甜', '15881050321', '四川省', '成都市', '武侯区', '幸福梅林9号', '0', '1532572015');
INSERT INTO `user_address` VALUES ('11', '10011', '林先生', '15881050779', '四川省', '成都市', '成华区', '尚城嘉园11号', '0', '1532572021');
INSERT INTO `user_address` VALUES ('12', '10011', '林海', '15881050779', '四川省', '成都市', '高新区', '天府三街69号', '0', '1532572885');
INSERT INTO `user_address` VALUES ('13', '10011', '林海', '15881050779', '四川省', '成都市', '高新区', '天府三街69号', '1', '1532576700');
INSERT INTO `user_address` VALUES ('14', '10013', '王梅', '18380526358', '四川省', '成都市', '成华区', '天府大道', '0', '1536715890');
INSERT INTO `user_address` VALUES ('15', '10013', '杨东', '15258523695', '四川省', '成都市', '成华区', '电子科大', '1', '1536716075');
INSERT INTO `user_address` VALUES ('16', '10014', '胖胖', '18200000000', '四川省', '成都市', '锦江区', '锦江大道金小花园路', '1', '1537321315');
INSERT INTO `user_address` VALUES ('17', '10014', '谁谁吧', '18290909090', '四川省', '成都市', '武侯区', '武侯大道和平解放路', '0', '1537321712');

-- ----------------------------
-- Table structure for `webconfig`
-- ----------------------------
DROP TABLE IF EXISTS `webconfig`;
CREATE TABLE `webconfig` (
  `web` varchar(20) NOT NULL COMMENT '网站配置标识',
  `name` varchar(200) NOT NULL COMMENT '网站名称',
  `keywords` text COMMENT '关键词',
  `desc` text COMMENT '描述',
  `is_log` int(1) NOT NULL DEFAULT '1' COMMENT '1开启日志0关闭',
  `file_type` varchar(200) DEFAULT NULL COMMENT '允许上传的类型',
  `file_size` bigint(20) DEFAULT NULL COMMENT '允许上传的最大值',
  `statistics` text COMMENT '统计代码',
  `black_ip` text COMMENT 'ip黑名单',
  `url_suffix` varchar(20) DEFAULT NULL COMMENT 'url伪静态后缀',
  KEY `web` (`web`) USING BTREE
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of webconfig
-- ----------------------------
INSERT INTO `webconfig` VALUES ('web', 'Tplay后台管理框架', 'Tplay,后台管理,thinkphp5,layui', 'Tplay是一款基于ThinkPHP5.0.12 + layui2.2.45 + ECharts + Mysql开发的后台管理框架，集成了一般应用所必须的基础性功能，为开发者节省大量的时间。', '1', 'jpg,png,gif,mp4,zip,jpeg', '500', '', '', null);
